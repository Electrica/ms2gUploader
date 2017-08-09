<?php
require_once MODX_CORE_PATH . 'components/ms2gallery/processors/mgr/gallery/upload.class.php';
class ms2guploaderResourceFileUploadProcessor extends msResourceFileUploadProcessor {

	private $resource = 0;
	public $ms2Gallery;
    public $mediaSource;
    public $languageTopics = array('ms2gallery:default');

	public function initialize() {

        $id = $this->getProperty('tid', @$_GET['id']);
        if (!$resource = $this->modx->getObject('modResource', $id)) {
			$resource = $this->modx->newObject('modResource');
			$resource->set('id', 0);
        }

		$source = $this->getProperty('source');
        $ctx = $resource->get('context_key');

        if (!$this->ms2Gallery = $this->modx->getService('ms2gallery', 'ms2Gallery',
            MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/')
        ) {
            return 'Could not load class ms2Gallery!';
        } elseif (!$this->mediaSource = $this->ms2Gallery->initializeMediaSource($ctx, $source)) {
            return $this->modx->lexicon('ms2gallery_err_no_source');
        }
        $this->resource = $resource;
        return true;
    }


	public function process()
    {
        if (!$data = $this->handleFile()) {
            return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_ns'));
        }

        $properties = $this->mediaSource->getPropertyList();
        $pathinfo = $this->ms2Gallery->pathinfo($data['name']);
        $extension = strtolower($pathinfo['extension']);
        $filename = strtolower($pathinfo['filename']);

        $image_extensions = $allowed_extensions = array();
        if (!empty($properties['imageExtensions'])) {
            $image_extensions = array_map('trim', explode(',', strtolower($properties['imageExtensions'])));
        }
        if (!empty($properties['allowedFileTypes'])) {
            $allowed_extensions = array_map('trim', explode(',', strtolower($properties['allowedFileTypes'])));
        }
        if (!empty($allowed_extensions) && !in_array($extension, $allowed_extensions)) {
            @unlink($data['tmp_name']);

            return $this->failure($this->modx->lexicon('ms2gallery_err_wrong_ext'));
        } else {
            if (in_array($extension, $image_extensions)) {
                if (empty($data['properties']['height']) || empty($data['properties']['width'])) {
                    @unlink($data['tmp_name']);

                    return $this->failure($this->modx->lexicon('ms2gallery_err_wrong_image'));
                }
                $type = 'image';
            } else {
                $type = $extension;
            }
        }

        if ($this->modx->getOption('ms2gallery_duplicate_check', null, true, true)) {
            if ($this->modx->getCount('msResourceFile',
                array('resource_id' => $this->resource->id, 'hash' => $data['hash'], 'parent' => 0))
            ) {
                @unlink($data['tmp_name']);

                return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_exists'));
            }
        }

        $filename = !empty($properties['imageNameType']) && $properties['imageNameType'] == 'friendly'
            ? $this->resource->cleanAlias($filename)
            : $data['hash'];
        $filename = str_replace(',', '', $filename) . '.' . $extension;
        $tmp_filename = $filename;
        $i = 1;
        while (true) {
            if (!$count = $this->modx->getCount('msResourceFile',
                array('resource_id' => $this->resource->id, 'file' => $tmp_filename, 'parent' => 0))
            ) {
                $filename = $tmp_filename;
                break;
            } else {
                $pcre = '#(-' . ($i - 1) . '|)\.' . $extension . '$#';
                $tmp_filename = preg_replace($pcre, "-$i.$extension", $tmp_filename);
                $i++;
            }
        }

        $rank = isset($properties['imageUploadDir']) && empty($properties['imageUploadDir'])
            ? 0
            : $this->modx->getCount('msResourceFile', array('parent' => 0, 'resource_id' => $this->resource->id));

		// каталог загрузки изображений
		$path = $this->resource->id . '/';
		if($this->resource->id == 0) $path = '0/' . $this->modx->user->id . '/';

        /** @var msResourceFile $uploaded_file */
        $uploaded_file = $this->modx->newObject('msResourceFile', array(
            'resource_id' => $this->resource->id,
            'parent' => 0,
            'name' => preg_replace('#\.' . $extension . '$#i', '', $data['name']),
            'file' => $filename,
            'path' => $path,
            'source' => $this->mediaSource->get('id'),
            'type' => $type,
            'rank' => $rank,
            'createdon' => date('Y-m-d H:i:s'),
            'createdby' => $this->modx->user->id,
            'active' => 1,
            'hash' => $data['hash'],
            'properties' => $data['properties'],
        ));

        $this->mediaSource->createContainer($uploaded_file->get('path'), '/');
        $this->mediaSource->errors = array();
        if ($this->mediaSource instanceof modFileMediaSource) {
            $upload = $this->mediaSource->createObject($uploaded_file->get('path'), $uploaded_file->get('file'), '');
            if ($upload) {
                copy($data['tmp_name'], urldecode($upload));
            }
        } else {
            $data['name'] = $filename;
            $upload = $this->mediaSource->uploadObjectsToContainer($uploaded_file->get('path'), array($data));
        }
        @unlink($data['tmp_name']);

        if ($upload) {
            $url = $this->mediaSource->getObjectUrl($uploaded_file->get('path') . $uploaded_file->get('file'));
            $uploaded_file->set('url', $url);
            $uploaded_file->save();

            if (empty($rank)) {
                $imagesTable = $this->modx->getTableName('msResourceFile');
                $sql = "UPDATE {$imagesTable} SET rank = rank + 1 WHERE resource_id ='" . $this->resource->id . "' AND id !='" . $uploaded_file->get('id') . "'";
                $this->modx->exec($sql);
            }

            $generate = $uploaded_file->generateThumbnails($this->mediaSource);
            if ($generate !== true) {
                $this->modx->log(modX::LOG_LEVEL_ERROR,
                    'Could not generate thumbnails for image with id = ' . $uploaded_file->get('id') . '. ' . $generate);

                return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_thumb'));
            } else {
                return $this->success('', $uploaded_file);
            }
        } else {
            return $this->failure($this->modx->lexicon('ms2gallery_err_gallery_save') . ': ' . print_r($this->mediaSource->getErrors(),
                    1));
        }
    }




}
return 'ms2guploaderResourceFileUploadProcessor';
