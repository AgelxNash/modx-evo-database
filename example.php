<?php
include_once 'vendor/autoload.php';

$DB = new AgelxNash\Modx\Evo\Database\Database(
    'localhost',
    'modx',
    'homestead',
    'secret',
    'modx_',
    'utf8mb4',
    'SET NAMES'
);
$DB->setDebug(true);
try {
    $DB->connect();
    print ' [ CONNECTION TIME ] ' . $DB->getConnectionTime(true) . ' s. ' . PHP_EOL;
    print ' [ VERSION ] '. $DB->getVersion() . PHP_EOL;

    $result = $DB->query("SELECT * FROM " . $DB->getFullTableName('site_content') . " WHERE parent = 0");
    foreach ($DB->makeArray($result) as $item) {
        print ' [ DOCUMENT #ID ' . $item['id'] . ' ] ' . $item['pagetitle'] . PHP_EOL;
    }

    foreach ($DB->getAllExecutedQuery() as $id => $query) {
        print ' [ QUERY #'. $id . ' ] ' . PHP_EOL;
        foreach ($query as $key => $data) {
            print "\t [" . $key . '] ' . $data . PHP_EOL;
        }
    }
    print ' [ DONE ] ' . PHP_EOL;

} catch (Exception $exception) {
    echo get_class($exception) . PHP_EOL;
    echo "\t" . $exception->getMessage() . PHP_EOL;
    echo $exception->getTraceAsString() . PHP_EOL;
    exit(1);
}
