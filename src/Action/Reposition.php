<?php
namespace IjorTengab\FilesBulkOperation\Action;

use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Reposition
{
    /**
     * Instance of Symfony\Component\Filesystem\Filesystem.
     */
    protected $filesystem;

    /**
     * String. Informasi yang akan dilakukan oleh instance object ini.
     * Options available: move.
     */
    protected $action;

    /**
     * Simulated the action.
     */
    protected $dry_run = false;

    /**
     * Jika true, maka object Symfony\Component\Finder\Finder hanya akan mencari
     * files saja.
     */
    protected $files = false;

    /**
     * Jika true, maka object `Finder` hanya akan mencari
     * directory saja.
     */
    protected $directory = false;

    /**
     * Directory tempat mencari files yang akan dilakukan reposition.
     */
    protected $working_directory_lookup = null;

    /**
     * Deep level.
     */
    protected $working_directory_lookup_level = null;

    /**
     * Directory tempat menampung hasil reposition files.
     */
    protected $working_directory_destination = null;

    protected $filename_pattern = null;

    protected $directory_destination_pattern = null;

    protected $directory_destination_alt_pattern = null;

    protected $directory_destination_default = null;

    /**
     * Construct instance.
     */
    public function __construct()
    {
        $this->filesystem = new Filesystem;
        return $this;
    }

    /**
     * Memberi tanda bahwa instance ini akan melakukan action moving files.
     */
    public function move()
    {
        $this->action = 'move';
        return $this;
    }

    /**
     * Memberi tanda bahwa instance ini akan melakukan action moving files.
     */
    public function dryRun()
    {
        $this->dry_run = true;
        return $this;
    }

    /**
     * Set property `$working_directory_lookup`. Validasi akan dilakukan oleh
     * object `Finder`.
     */
    public function setWorkingDirectoryLookup($dir, $level = null)
    {
        $this->working_directory_lookup = $dir;
        $this->working_directory_lookup_level = $level;
        return $this;
    }

    /**
     * Set property `$working_directory_destination`. Validasi akan dilakukan
     * oleh object `Finder`.
     */
    public function setWorkingDirectoryDestination($dir)
    {
        $this->working_directory_destination = $dir;
        return $this;
    }

    /**
     * Set property `$filename_pattern`. Sekaligus otomatis bahwa object
     * `Finder` hanya akan mencari file saja.
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
    public function setDirectoryDestinationPattern($pattern, $alt_pattern = null)
    {
        $this->directory_destination_pattern = $pattern;
        $this->directory_destination_alt_pattern = $alt_pattern;
        return $this;
    }

    /**
     *
     */
    public function setDirectoryDestinationDefault($pattern)
    {
        $this->directory_destination_default = $pattern;
        return $this;
    }


    /**
     *
     */
    protected function validate()
    {
        if (null === $this->working_directory_lookup) {
            throw new \RuntimeException('Direktori Lookup belum didefinisikan.');
        }
        if (null === $this->working_directory_destination) {
            throw new \RuntimeException('Direktori Destination belum didefinisikan.');
        }
        if (
            null === $this->working_directory_lookup_level &&
            Path::canonicalize($this->working_directory_lookup) != Path::canonicalize($this->working_directory_destination) &&
            Path::isBasePath($this->working_directory_lookup, $this->working_directory_destination))
        {
            throw new \RuntimeException('Direktori Destination tidak boleh berada di dalam Direktori Lookup.');
        }
        switch ($this->action) {
            case 'move':

                break;

            default:
                throw new \RuntimeException('Action Reposition belum didefinisikan.');
        }
    }

    /**
     *
     */
    public function execute()
    {
        $this->validate();
        // Start object `Finder`.
        $finder = new Finder();
        $finder->in($this->working_directory_lookup);
        $finder->ignoreDotFiles(false);
        if ($this->files) {
            $finder->files();
        }
        $working_directory_lookup_level = $this->working_directory_lookup_level;
        if (null !== $this->working_directory_lookup_level) {
            $finder->depth($this->working_directory_lookup_level);
        }
        if (null !== $this->filename_pattern) {
            $finder->name($this->filename_pattern);
        }
        // Action.
        switch ($this->action) {
            case 'move':
                foreach ($finder as $file) {
                    $this->actionMove($file);
                }
                break;
        }
    }

    protected function actionMove($file)
    {
        $directory_destination = $this->working_directory_destination;
        // Directory destination must replace if pattern has been set.
        $directory_destination_alt_pattern = false;
        $directory_destination_default = true;

        if ($this->directory_destination_pattern !== null) {
            $directory_destination_pattern = preg_replace($this->filename_pattern, $this->directory_destination_pattern, $file->getFilename());
            $dirs = Finder::create()
                ->directories()
                ->path($directory_destination_pattern)
                ->in($this->working_directory_destination);
            if ($dirs->count() == 1) {
                $dirs = iterator_to_array($dirs);
                $dir = array_shift($dirs);
                $directory_destination = Path::join($directory_destination, $dir->getRelativePathname());
                $directory_destination_default = false;
            }
            elseif ($this->directory_destination_alt_pattern !== null) {
                $directory_destination_alt_pattern = true;
            }
        }

        if ($directory_destination_alt_pattern) {
            $directory_destination_alt_pattern = preg_replace($this->filename_pattern, $this->directory_destination_alt_pattern, $file->getFilename());
            $dirs = Finder::create()
                ->directories()
                ->path($directory_destination_pattern)
                ->in($this->working_directory_destination);
            if ($dirs->count() == 1) {
                $dirs = iterator_to_array($dirs);
                $dir = array_shift($dirs);
                $directory_destination = Path::join($directory_destination, $dir->getRelativePathname());
                $directory_destination_default = false;
            }
        }

        if ($this->directory_destination_default !== null && $directory_destination_default) {
            $directory_destination_default = preg_replace($this->filename_pattern, $this->directory_destination_default, $file->getFilename());
            $directory_destination = Path::join($directory_destination, $directory_destination_default);
        }

        // die('stop');

        $last_modified = $file->getMTime();
        $source = Path::join($this->working_directory_lookup, $file->getRelativePathname());
        $destination = Path::join($directory_destination, $file->getFilename());
        $base_path = PATH::getLongestCommonBasePath([$source, $destination]);
        $log  = 'Moving.' . PHP_EOL;
        $log .= '  From: ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($source, $base_path) . PHP_EOL;
        $log .= '  To:   ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($destination, $base_path) . PHP_EOL;
        if (file_exists($destination)) {
            $info_source['size'] = filesize($source);
            $info_source['modified'] = filemtime($source);
            $info_destination['size'] = filesize($destination);
            $info_destination['modified'] = filemtime($destination);
            $log .= '  Destination has exists. ';
            if ($info_source['size'] == $info_destination['size']) {
                $log .= 'Size is equals. ';
            }
            else{
                $log .= 'Size: source '.filesize($source).', destination '.filesize($destination).'. ';
            }
            if ($info_source['modified'] == $info_destination['modified']) {
                $log .= 'Date modified is equals. ';
            }
            else{
                $log .= 'Date modified: source '.date('Y-m-d H:i:s', $info_source['modified']).', destination '.date('Y-m-d H:i:s', $info_destination['modified']).'. ';
            }
            $log .= PHP_EOL;
        }
        else {
            if ($this->dry_run === false) {
                $prepare_directory = Path::getDirectory($destination);
                $prepare = $this->filesystem->mkdir($prepare_directory);
                $this->filesystem->rename($source, $destination);
                if ($last_modified != filemtime($destination)) {
                    $result = @touch($destination, $last_modified);
                    if (false === $result) {
                        // @todo. Beri tahu via log kalo ada kegagalan touch
                        // dan simpan informasi $last_modified untuk tindakan
                        // alternative.
                    }
                }
            }
        }
        echo $log;

    }
}
