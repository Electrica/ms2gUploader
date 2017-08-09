<?php
if($modx->context->key == 'mgr') return;
switch($modx->event->name) {
	case 'OnDocFormSave':
		if($mode == 'new' && $resource->class_key == 'Ticket') {
			$ms2Gallery = $modx->getService('ms2gallery', 'ms2Gallery', MODX_CORE_PATH . 'components/ms2gallery/model/ms2gallery/');
			$c = $modx->newQuery('msResourceFile');
			$c->where(array(
				'resource_id' => 0,
				'createdby' => $modx->user->id
			));
			$files = $modx->getCollection('msResourceFile', $c);

			// если нет файлов идем дальше
			if(!empty($files)) {
				$mediaSource = $modx->getObject('sources.modMediaSource', $modx->getOption('ms2gallery_source_default'));
				$mediaSource->set('ctx', $modx->context->key);
				$mediaSource->initialize();
				$mediaSource->renameContainer('0/' . $modx->user->id, $resource->id);
				if(!$mediaSource->moveObject('0/' . $resource->id, '/')) {
					$modx->log(1, 'Не удалось переместить файлы');
					$modx->log(1, $modx->user->id);
					$modx->log(1, $resource->id);
				}

				$subPath = '0/' . $modx->user->id . '/';

				foreach ($files as $item) {
					$file = $item->get('file');
					$path = $item->get('path');
					$item->set('resource_id', $id);
					$thumbSize = substr($path, strlen($subPath));
					if($thumbSize) {
						$thumbPath = $id . '/' . $thumbSize;
						$item->set('path', $thumbPath);
						$item->set('url', $mediaSource->getObjectUrl($thumbPath. $file));
					}else{
						$item->set('path', $id . '/');
						$item->set('url', $mediaSource->getObjectUrl($id . '/' . $file));
					}
					$item->save();
				}
			}
		}
		break;
}
