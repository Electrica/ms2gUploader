<?php

class ms2guploaderProductFileDeleteProcessor extends modObjectProcessor {
  public $classKey = 'msResourceFile';
  public $languageTopics = array('ms2guploader:default');
  public $mediaSource;

   public function process() {
    $id = $this->getProperty('id');
    if (!$file = $this->modx->getObject($this->classKey, $id)) {
      return $this->failure('Файл не найден');
    }
    /* elseif ($file->createdby != $this->modx->user->id) {
      return $this->failure($this->modx->lexicon('ms2guploader_err_file_owner'));
    } */

    if($file->get('resource_id') == 0){
      // initializeMediaSource
      $mediaSource = $this->modx->getObject('sources.modMediaSource', $this->getProperty('source'));
      $mediaSource->set('ctx', $this->modx->context->key);
      if ($mediaSource->initialize()) {
        $this->mediaSource = $mediaSource;
      }else{
        return $this->failure($this->modx->lexicon('ms2guploader_err_source_initialize'));
      }
      //remove files
      if (!$this->mediaSource->removeObject($file->get('path') . $file->get('file'))) {
        $this->modx->log(xPDO::LOG_LEVEL_ERROR,
          'Could not remove file at "' . $file->get('path') . $file->get('file') . '": ' . $this->mediaSource->errors['file']
        );
      }
      $children = $this->modx->getIterator('msResourceFile', array('parent' => $file->get('id')));
      /** @var msProductFile $child */
      foreach ($children as $child) {
        if (!$this->mediaSource->removeObject($child->get('path') . $child->get('file'))) {
          $this->modx->log(xPDO::LOG_LEVEL_ERROR,
            'Could not remove file at "' . $child->get('path') . $child->get('file') . '": ' . $this->mediaSource->errors['file']
          );
        }
      }
      //remove objects
      $result = $this->modx->exec("DELETE FROM {$this->modx->getTableName('msResourceFile')} WHERE `id` = {$id} OR `parent` = {$id};");

    }else{
      $result = $file->remove();
    }

    if(!$result){
      return $this->failure($this->modx->lexicon('ms2guploader_err_file_ns'));
    }
    return $this->success();
  }

}
return 'ms2guploaderProductFileDeleteProcessor';