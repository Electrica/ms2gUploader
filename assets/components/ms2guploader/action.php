<?php
if (empty($_REQUEST['action'])) {
  die('Access denied');
}
else {
  $action = $_REQUEST['action'];
}

define('MODX_API_MODE', true);
require dirname(dirname(dirname(dirname(__FILE__)))) . '/index.php';

$modx->getService('error', 'error.modError');
$modx->getRequest();
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');
$modx->error->message = null;

$ctx = !empty($_REQUEST['ctx']) ? $_REQUEST['ctx'] : 'web';
if ($ctx != 'web') {
  $modx->switchContext($ctx);
}

$properties = array(
	'tpl' => $_REQUEST['tpl'],
	'thumbsize' => $_REQUEST['thumbsize'],
	'ctx' => $_REQUEST['ctx'],
	'source' => $_REQUEST['source']
);

$ms2guploader = $modx->getService('ms2guploader', 'ms2guploader', $modx->getOption('ms2guploader_core_path', null, $modx->getOption('core_path') . 'components/ms2guploader/') . 'model/ms2guploader/', $properties);

if ($modx->error->hasError() || !($ms2guploader instanceof ms2guploader)) die('Error');
switch ($action) {
  //case 'config/get': $response = $_SESSION['ms2guploader'][$_REQUEST['form_key']]; break;
  case 'gallery/upload': $response = $ms2guploader->upload($_POST);break;
  case 'gallery/delete': $response = $ms2guploader->delete($_POST['id']); break;
  case 'gallery/sort': $response = $ms2guploader->sort($_POST['rank']);break;
  case 'gallery/limit': $response = $ms2guploader->fileLimit();break;
  default:
    $message = $_REQUEST['action'] != $action ? 'tickets_err_register_globals' : 'tickets_err_unknown';
    $response = $modx->toJSON(array('success' => false, 'message' => $modx->lexicon($message)));
}

if (is_array($response)) {
  $response = $modx->toJSON($response);
}

@session_write_close();
exit($response);
