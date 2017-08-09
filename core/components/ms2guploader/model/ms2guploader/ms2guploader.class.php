<?php
class ms2guploader {

  public $modx;
  public $pdoTools;
  public $mediaSource;
  public $initialized = array();
  public $authenticated = false;

  function __construct(modX &$modx, array $config = array()) {
    $this->modx =& $modx;
	$ms2Gallery = $this->modx->getService('ms2gallery', 'ms2Gallery', MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/');
	$corePath = $this->modx->getOption('ms2guploader_core_path', $config, $this->modx->getOption('core_path') . 'components/ms2guploader/');
    $assetsUrl = $this->modx->getOption('ms2guploader_assets_url', $config, $this->modx->getOption('assets_url') . 'components/ms2guploader/');
    $actionUrl = $this->modx->getOption('ms2guploader_action_url', $config, $assetsUrl . 'action.php');

	if (empty($config['source'])) {
      $config['source'] = $this->modx->getOption('ms2gallery_source_default');
    }

    $connectorUrl = $assetsUrl . 'connector.php';
    $this->config = array_merge(array(
      'assetsUrl' => $assetsUrl,
	  'cssUrl' => $assetsUrl . 'css/',
	  'vendorUrl' => $assetsUrl . 'vendor/',
	  'connectorUrl' => $connectorUrl,
	  'actionUrl' => $actionUrl,
	  'modelPath' => $corePath . 'model/',
	  'corePath' => $corePath,
	  'cultureKey' => $this->modx->getOption('cultureKey'),
	  'json_response' => true,
    ), $config);

    $this->modx->lexicon->load('ms2guploader:default');
	if ($this->pdoTools = $this->modx->getService('pdoFetch')) {
        $this->pdoTools->setConfig($this->config);
    }
  }

public function initialize($ctx = 'web', $currentFiles = 1) {



	$this->config['ctx'] = $ctx;
   // $this->config['currentFiles'] = $currentFiles;
    $this->config['allowFiles'] = true;
    $this->initializeMediaSource($this->config['ctx']);


	if ($this->initialized[$ctx] || MODX_API_MODE) {
      return $this->config;
    }

	$this->config['sourceProperties'] = $this->mediaSource->properties;

	// ms2guploaderConfig
	$data = json_encode(array(
        'ctx' => $ctx,
		'source' => $this->config['source'],
		'vendorUrl' => $this->config['vendorUrl'],
		'assetsUrl' => $this->config['assetsUrl'],
		'actionUrl' => $this->config['actionUrl'],
		'cultureKey' => $this->config['cultureKey'],
		'uploadLimit' => $this->config['uploadLimit'],
		'tpl' => $this->config['tpl'],
		'thumbsize' => $this->config['thumbsize'],
    ), true);

    $this->modx->regClientStartupScript('<script type="text/javascript">ms2guploaderConfig = ' . $data . ';</script>', true);

	// css
	if ($css = trim($this->modx->getOption('ms2guploader_frontend_css'))) {
      $this->modx->regClientCSS($css);
    }

	// js
    if ($js = trim($this->modx->getOption('ms2guploader_frontend_js'))) {
      if (!empty($js) && preg_match('/\.js/i', $js)) {
        $jsCurl = $this->config['vendorUrl'] . 'curl/curl.js';
        $this->modx->regClientScript($jsCurl);
        $this->modx->regClientScript($js);
      }
    }

    $this->initialized[$ctx] = true;
	return $this->config;
  }




  public function upload($data) {
	$data['file'] = $_FILES['file'];
    $data['rank'] = $_REQUEST['rank'];
	$thumbsize = $data['thumbsize'];

    $response = $this->modx->runProcessor('web/gallery/upload', $data, array('processors_path' => dirname(dirname(dirname(__FILE__))) . '/processors/'));
    if ($response->isError()) {
      return $this->error($response->getMessage());
    }

	$file_id = $response->response['object']['id'];

	$q = $this->modx->newQuery('msResourceFile');
	$q->where(array(
		"parent" => $file_id,
		"path:LIKE" => "%/{$thumbsize}/",
	));
	$q->select('url');
	$q->prepare();
	$q->stmt->execute();
	$thumb = $q->stmt->fetch(PDO::FETCH_ASSOC);

	$tpl = $this->pdoTools->getChunk($this->config['tpl'], array(
		'id' => $file_id,
		'thumb' => $thumb['url'],
	));

	return $this->success('', array(
		'html' => $tpl,
		'file' => array(
			'id' => $file_id,
			'thumb' => $thumb['url'],
			'source' => $data['source'],
		),
	));

  }





  public function delete($file_id) {
  	if(!$this->modx->user->isAuthenticated()) return;
  	$response = $this->modx->runProcessor('web/gallery/delete', array('id' => $file_id, 'source' => $this->config['source']), array('processors_path' => dirname(dirname(dirname(__FILE__))) . '/processors/'));
    if ($response->isError()) {
      return $this->error($response->getMessage());
    }
    return $this->success('', array('id' => $file_id));
  }

 public function sort($rank) {
 	if(!$this->modx->user->isAuthenticated()) return;
  	$response = $this->modx->runProcessor('web/gallery/sort', array('rank' => $rank), array('processors_path' => dirname(dirname(dirname(__FILE__))) . '/processors/'));
  	if ($response->isError()) {
  		return $this->error($response->getMessage());
  	}
  	return $this->success();
  }






  /**
   * This method returns an error
   *
   * @param string $message A lexicon key for error message
   * @param array $data .Additional data, for example cart status
   * @param array $placeholders Array with placeholders for lexicon entry
   *
   * @return array|string $response
   */
  public function error($message = '', $data = array(), $placeholders = array())
  {
    //header('HTTP/1.1 400 Bad Request');
    $messageTranslation = $this->modx->lexicon($message, $placeholders);
    if($messageTranslation){
      $message = $messageTranslation;
    }
    $response = array(
      'success' => false
    , 'message' => $message
    , 'data' => $data
    );
    //$this->modx->log(modX::LOG_LEVEL_ERROR, $message);
    return $this->config['json_response']
      ? $this->modx->toJSON($response)
      : $response;
  }



	public function success($message = '', $data = array(), $placeholders = array()) {
	    $response = array(
	        'success' => true,
	        'message' => $this->modx->lexicon($message, $placeholders),
	        'data' => $data,
	    );
	    return $this->config['json_response']
	        ? json_encode($response)
	        : $response;
	}





  public function initializeMediaSource($ctx = '')
  {
    if (is_object($this->mediaSource) && $this->mediaSource instanceof modMediaSource) {
      return $this->mediaSource;
    } else {
      if ($this->mediaSource = $this->modx->getObject('sources.modMediaSource', $this->config['source'])) {
        if (empty($ctx)) {
          $ctx = $this->config['ctx'];
        }
        $this->mediaSource->set('ctx', $ctx);
        $this->mediaSource->initialize();
        $this->mediaSource->properties = $this->mediaSource->getProperties();
        return $this->mediaSource;
      } else {
        return false;
      }
    }
  }







}
