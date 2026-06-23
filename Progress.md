## Priority build order:

schema/zatcher_schema.sql — database first, everything else depends on it /
config/db.php — connection /
victim/report.php — how data gets IN /
analyst/upload_evidence.php — where LADINA gets triggered /
analyst/dashboard.php — intelligence view /
police/evidence.php + export_xml.php — law enforcement output /
auth/login.php — role-based access /
zicta/analytics.php — patterns dashboard /
