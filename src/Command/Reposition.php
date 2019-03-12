<?php
namespace IjorTengab\FilesAutoReposition\Command;

use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Reposition
{
    protected $filesystem;
    
    protected $action;
    
    protected $files = false;
    
    protected $directory = false;
    
    protected $working_directory_lookup = null;
    
    protected $working_directory_destionation = null;
    
    protected $filename_pattern = null;
    
    protected $directory_destionation_pattern = null;
    
    protected $directory_destionation_default_pattern = null;
    
    /**
     * 
     */
    public function __construct()
    {
        $this->filesystem = new Filesystem;
        return $this; 
    }
    
    /**
     * 
     */
    public function move()
    {
        $this->action = 'move';
        return $this; 
    }
    
    /**
     * 
     */
    public function setWorkingDirectoryLookup($dir)
    {
        $this->working_directory_lookup = $dir;
        return $this; 
    }
    
    /**
     * 
     */
    public function setWorkingDirectoryDestionation($dir)
    {
        $this->working_directory_destionation = $dir;
        return $this; 
    }
    
    /**
     * 
     */
    public function setFileNamePattern($pattern)
    {
        $this->files = true;
        $this->filename_pattern = $pattern;
        return $this; 
    }
    /**
     * 
     */
    public function setDirectoryDestionationPattern($pattern, $default_pattern = null)
    {
        $this->directory_destionation_pattern = $pattern;
        $this->directory_destionation_default_pattern = $default_pattern;
        return $this; 
    }
    
    /**
     * 
     */
    public function execute()
    {
        if (null === $this->working_directory_lookup) {
            throw new \RuntimeException('Direktori Lookup belum didefinisikan.');
        }
        if (null === $this->working_directory_destionation) {
            throw new \RuntimeException('Direktori Destination belum didefinisikan.');
        }
        if (
            Path::canonicalize($this->working_directory_lookup) != Path::canonicalize($this->working_directory_destionation) &&
            Path::isBasePath($this->working_directory_lookup, $this->working_directory_destionation)) 
        {
            throw new \RuntimeException('Direktori Destination tidak boleh berada di dalam Direktori Lookup.');
        }
        switch ($this->action) {
            case 'move':
                if ($this->directory_destionation_pattern === null) {
                    throw new \RuntimeException('Direktori Destination Pattern belum didefinisikan.');
                }
                break;
        
            case '':
                // Do something.
                break;
        
            default:
                // Do something.
                break;
        }
        $working_directory_destionation = $this->working_directory_destionation;
        $finder = new Finder();
        $finder->in($this->working_directory_lookup);
        if ($this->files) {
            $finder->files();
        }
        if (null !== $this->filename_pattern) {
            $finder->name($this->filename_pattern);
        }
        
        foreach ($finder as $file) {
            // dumps the absolute path
            // var_dump($file->getRealPath());
            // dumps the relative path to the file, omitting the filename
            // var_dump($file->getRelativePath());
            // dumps the relative path to the file
            // var_dump($file->getRelativePathname());
            // var_dump($file->getBasename());
            // var_dump($file->getFilename());
            $directory_destionation_pattern = preg_replace($this->filename_pattern, $this->directory_destionation_pattern, $file->getFilename());
            $directory_destionation_default_pattern = preg_replace($this->filename_pattern, $this->directory_destionation_default_pattern, $file->getFilename());
            // echo $directory_destionation_pattern . PHP_EOL;
            // echo $directory_destionation_default_pattern . PHP_EOL;
            // echo '-' . PHP_EOL;
            $dirs = Finder::create()
                    ->directories()
                    ->name($directory_destionation_pattern)
                    ->in($this->working_directory_destionation);
            if ($dirs->hasResults()) {
                // echo 'hasResults' . PHP_EOL;
                if ($dirs->count() > 1) {
                    // todo, beri log bahwa kita ambil yang teratas.
                    // echo $dirs->count() . PHP_EOL;
                }
                $dirs = iterator_to_array($dirs);
                $dir = array_shift($dirs);
                $directory_destionation = $dir->getFilename();
            }
            else {
                $directory_destionation = $directory_destionation_default_pattern;
            }
            if (empty($directory_destionation)) {
                // beri log bahwa gak ada tuh.
            }
            else {
                $source = Path::join($this->working_directory_lookup, $file->getRelativePathname());
                $destionation = Path::join($this->working_directory_destionation, $directory_destionation, $file->getFilename());
                // echo "$source => PHP_EOL $destionation" . PHP_EOL;
                $base_path = PATH::getLongestCommonBasePath([$source, $destionation]);
                // $debugname = 'base_path'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                // echo PATH::makeRelative($source, $base_path);
                $log  = 'Moving.' . PHP_EOL;
                $log .= '  From: ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($source, $base_path) . PHP_EOL;
                $log .= '  To:   ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($destionation, $base_path) . PHP_EOL;
                echo $log;
                $prepare_directory = Path::getDirectory($destionation);
                $prepare = $this->filesystem->mkdir($prepare_directory);
                $this->filesystem->rename($source, $destionation);
            }
            
            // $debugname = 'dirs'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
            // ->hasResults();
            // foreach ($dirs as $dir) {
                // $debugname = 'dir'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                // var_dump($dir->getFilename());

            // }
            // break;
        }

        return $this; 
    }
    
}
