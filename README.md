## DBAPI Evolution
[![CMS MODX Evolution](https://img.shields.io/badge/CMS-MODX%20Evolution-brightgreen.svg)](https://github.com/modxcms/evolution) [![Build Status](https://api.travis-ci.org/AgelxNash/modx-evo-database.svg?branch=master)](https://travis-ci.org/AgelxNash/modx-evo-database) [![Code quality](https://img.shields.io/scrutinizer/g/AgelxNash/modx-evo-database.svg?maxAge=2592000)](https://scrutinizer-ci.com/g/AgelxNash/modx-evo-database/) [![Code Coverage](https://scrutinizer-ci.com/g/AgelxNash/modx-evo-database/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/AgelxNash/modx-evo-database/?branch=master) [![Total Downloads](https://poser.pugx.org/agelxnash/modx-evo-database/d/total.png)](https://packagist.org/packages/agelxnash/modx-evo-database) [![License](https://img.shields.io/github/license/AgelxNash/modx-evo-database.svg?maxAge=2592000)](https://github.com/AgelxNash/modx-evo-database/blob/master/LICENSE)

### Example
---------
```php
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
$DB->connect();
$table = $DB->getFullTableName('site_content');

$result = $DB->query('SELECT * FROM ' . $table . ' WHERE parent = 0 ORDER BY pagetitle DESC LIMIT 10');
    // or
$result = $DB->select('*', $table, 'parent = 0', 'pagetitle DESC', '10');
    // or
$result = $DB->select(
        ['id', 'pagetitle', 'title' => 'longtitle'],
        ['c' => $table],
        ['parent = 0'],
        'ORDER BY pagetitle DESC',
        'LIMIT 10'
    );
foreach ($DB->makeArray($result) as $item) {
    echo "\t [ DOCUMENT #ID " . $item['id'] . ' ] ' . $item['pagetitle'] . PHP_EOL;
}
```

### Author
---------
<table>
  <tr>
    <td valign="center" align="center"><img src="http://www.gravatar.com/avatar/bf12d44182c98288015f65c9861903aa?s=250"></td>
	<td valign="top">
		<h4>Borisov Evgeniy
		<br />
		Agel_Nash</h4>
		<br />
	    Laravel, MODX, Security Audit
		<br />
		<bt />
		<br />
        <small>
            <a href="https://agel-nash.ru">https://agel-nash.ru</a>
		    <br />
		    <strong>Telegram</strong>: <a href="https://t.me/Agel_Nash">@agel_nash</a>
		    <br />
		    <strong>Email</strong>: modx@agel-nash.ru
		</small>
	</td>
  </tr>
</table>
