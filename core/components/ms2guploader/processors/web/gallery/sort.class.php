<?php
class ms2guploaderProductFileSortProcessor extends modObjectProcessor {
    public $classKey = 'msResourceFile';

	/** {@inheritDoc} */
    public function initialize() {
       return true;
    }


    /** {@inheritDoc} */
    public function process() {
        $rank = $this->getProperty('rank');
		if(empty($rank))  return $this->success();
        /** @var msProductFile $files */
        foreach($rank as $idx => $id){
            if (!$file = $this->modx->getObject($this->classKey, $id)) {
                return $this->failure($this->modx->lexicon('ms2guploader_err_file_ns'));
            }


            $file->set('rank', $idx-1);
            $file->save();

            foreach ($this->modx->getIterator('msResourceFile', array('parent' => $file->get('id'))) as $child) {
                $child->set('rank', $idx-1);
                $child->save();
            }
        }

        return $this->success();
    }

}
return 'ms2guploaderProductFileSortProcessor';
