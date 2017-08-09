<?php
$ms2guploader = $modx->getService('ms2guploader', 'ms2guploader', $modx->getOption('ms2guploader_core_path', null, $modx->getOption('core_path') . 'components/ms2guploader/') . 'model/ms2guploader/', $scriptProperties);
$pdoFetch = $modx->getService('pdoFetch');
$source = $source ?: $modx->getOption('ms2gallery_source_default', null, 3);

// Проверка на сущестование ресурса
if(empty($tid)) $tid = !empty($_REQUEST[$getParam]) ? (int)$_REQUEST[$getParam] : 0;
if($tid != 0) $resource = $modx->getObject($class, $tid);

$count = 0;
$q = $modx->newQuery('msResourceFile');
$q->innerJoin('msResourceFile', 'Thumb', 'Thumb.parent = msResourceFile.id');
$q->where(array(
	'resource_id' => ($resource && !$resource->deleted) ? $tid : 0,
	'parent' => 0,
	'Thumb.path:LIKE' => "%/{$thumbsize}/",
	'source' => $source
));
$q->sortby('msResourceFile.rank', 'ASC');
$q->select('msResourceFile.id, Thumb.url as thumb');
if($q->prepare() && $q->stmt->execute()) {
	$files = $q->stmt->fetchAll(PDO::FETCH_ASSOC);
	$count = count($files);
	$_files = '';
	foreach($files as $file) {
		$_files .= $pdoFetch->getChunk($tpl, $file);
	}
}
$properties = array();
$properties = $ms2guploader->initialize($modx->context->key);
$output = $pdoFetch->getChunk($tplOuter, array_merge($properties, array(
	'files' => $_files,
	'disabled' => $count >= $uploadLimit ? 'disabled'  : '',
)));
return $output;