<?php

class FileTreeFactory extends AbstractTreeFactory
{
    private $files = array();
    
    /**
     *
     * @param $path string            
     * @param $extension string            
     * @return void
     */
    public function scan($path, $extension = 'php')
    {
        try {
            $directory_iterator = new RecursiveDirectoryIterator($path);
            $recursive_iterator = new RecursiveIteratorIterator($directory_iterator);
            $filter_iterator = new FileInfoFilterIterator($recursive_iterator);
            $filter_iterator->setFindExtension($extension);
            $path_length = strlen($path);
            foreach ($filter_iterator as $file) {
                    $this->addFile($file->getPathname());
            }
        } catch (UnexpectedValueException $e) {
            echo "Could not open dir $path" . PHP_EOL;
        } catch (Exception $e) {
            die($e->getMessage());
        }
        return $this;
    }

    /**
     *
     * @param $filename string            
     */
    public function addFile($filename)
    {
        $this->files[] = new Node($filename);
        return $this;
    }

    public function &produceList()
    {
        return $this->files;
    }
}
