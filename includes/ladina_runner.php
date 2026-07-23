<?php
// includes/ladina_runner.php
//
// Shared helper for shelling out to the LADINA Python engine
// (ai/ZatcherAnalyzer.py). Used by analyst/upload_evidence.php (automatic
// run right after a file lands) and analyst/dashboard.php (manual re-run
// action for diagnosis or hands-on correction). One implementation so
// both entry points behave identically.

/**
 * Runs LADINA against a single evidence file and returns its parsed JSON
 * report, or null if it isn't available/configured/failed. Never fatal —
 * evidence upload and case review must keep working even if LADINA (or
 * its GEMINI_API_KEY) isn't set up on this machine; the caller decides
 * how to surface that to the analyst.
 */
function runLadinaAnalysis(string $filePath): ?array {
    if (!is_file($filePath)) {
        return null;
    }

    if (!ladinaIsConfigured()) {
        return null;
    }

    $script = escapeshellarg(__DIR__ . '/../ai/ZatcherAnalyzer.py');
    $file   = escapeshellarg($filePath);

    // ZatcherAnalyzer.py saves its report as a RELATIVE path —
    // Path(path.stem + "_zatcher_intel.json") — resolved against
    // whatever the Python process's cwd happens to be. path.stem also
    // strips the extension (abc123.png -> abc123). So the working
    // directory MUST be the evidence file's own folder, and the
    // filename we look for must match exactly: no extension, no
    // directory prefix. (This used to be wrong on both counts — cwd
    // was set to ai/, and PHP looked for the full filename+extension
    // still attached — so this always reported "failed" even when
    // Gemini succeeded and wrote the file, just somewhere else.)
    $workingDir = dirname($filePath);
    $stem = pathinfo($filePath, PATHINFO_FILENAME);
    $outJson = $workingDir . '/' . $stem . '_zatcher_intel.json';

    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

    // IMPORTANT: proc_open's $env argument REPLACES the child's entire
    // environment rather than adding to it. Passing just
    // ['GEMINI_API_KEY' => ...] here was wiping out PATH (and
    // everything else) for the Python process — so if `php -S` was
    // launched from a shell with a virtualenv active (PATH pointing at
    // e.g. .../cs50-env/bin first), that PATH never reached the child,
    // and it silently fell back to the system python3 instead — which
    // may not have the same packages installed as the venv one. Fix:
    // start from the current process's full environment and layer
    // GEMINI_API_KEY on top, so PATH (and anything else, like a venv)
    // carries through.
    $env = getenv();
    if ($env === false) {
        $env = [];
    }
    $env['GEMINI_API_KEY'] = getenv('GEMINI_API_KEY');

    $process = proc_open("python3 {$script} {$file}", $descriptors, $pipes, $workingDir, $env);
    if (!is_resource($process)) {
        return null;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    if (!is_file($outJson)) {
        // Log what Python actually said, so "failed" is diagnosable
        // from the PHP error log instead of a dead end.
        error_log("LADINA: expected output not found at {$outJson}\nstdout: {$stdout}\nstderr: {$stderr}");
        return null;
    }

    $report = json_decode(file_get_contents($outJson), true);
    @unlink($outJson);

    return $report ?: null;
}

/**
 * Whether the server has GEMINI_API_KEY set — the one prerequisite for
 * LADINA to run at all. Surfaced to the dashboard so an analyst can tell
 * "LADINA isn't configured" apart from "LADINA tried and failed".
 */
function ladinaIsConfigured(): bool {
    return (bool) getenv('GEMINI_API_KEY');
}
