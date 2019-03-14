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
     * Directory tempat menampung hasil reposition files.
     */
    protected $working_directory_destination = null;

    protected $filename_pattern = null;

    protected $directory_destination_pattern = null;

    protected $directory_destination_default_pattern = null;

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
     * Set property `$working_directory_lookup`. Validasi akan dilakukan oleh
     * object `Finder`.
     */
    public function setWorkingDirectoryLookup($dir)
    {
        $this->working_directory_lookup = $dir;
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
    public function setDirectoryDestinationPattern($pattern, $default_pattern = null)
    {
        $this->directory_destination_pattern = $pattern;
        $this->directory_destination_default_pattern = $default_pattern;
        return $this;
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
        if ($this->files) {
            $finder->files();
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
            Path::canonicalize($this->working_directory_lookup) != Path::canonicalize($this->working_directory_destination) &&
            Path::isBasePath($this->working_directory_lookup, $this->working_directory_destination))
        {
            throw new \RuntimeException('Direktori Destination tidak boleh berada di dalam Direktori Lookup.');
        }
        switch ($this->action) {
            case 'move':
                if ($this->directory_destination_pattern === null) {
                    throw new \RuntimeException('Direktori Destination Pattern belum didefinisikan.');
                }
                break;

            default:
                throw new \RuntimeException('Action Reposition belum didefinisikan.');
        }
    }

    protected function actionMove($file)
    {
        $directory_destination_pattern = preg_replace($this->filename_pattern, $this->directory_destination_pattern, $file->getFilename());
        $directory_destination_default_pattern = preg_replace($this->filename_pattern, $this->directory_destination_default_pattern, $file->getFilename());
        // echo $directory_destination_pattern . PHP_EOL;
        // echo $directory_destination_default_pattern . PHP_EOL;
        // echo '-' . PHP_EOL;
        // Jika rename.
        $dirs = Finder::create()
                ->directories()
                ->name($directory_destination_pattern)
                ->in($this->working_directory_destination);
        if ($dirs->hasResults()) {
            if ($dirs->count() > 1) {
                // @todo. beri log bahwa kita ambil yang teratas.
                // echo $dirs->count() . PHP_EOL;
            }
            $dirs = iterator_to_array($dirs);
            $dir = array_shift($dirs);
            $directory_destination = $dir->getFilename();
        }
        else {
            $directory_destination = $directory_destination_default_pattern;
        }
        if (empty($directory_destination)) {
            // @todo. Directory destination tidak ada, beri log.
        }
        else {
            $last_modified = $file->getMTime();
            $source = Path::join($this->working_directory_lookup, $file->getRelativePathname());
            $destination = Path::join($this->working_directory_destination, $directory_destination, $file->getFilename());
            $base_path = PATH::getLongestCommonBasePath([$source, $destination]);
            $log  = 'Moving.' . PHP_EOL;
            $log .= '  From: ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($source, $base_path) . PHP_EOL;
            $log .= '  To:   ' . $base_path . PHP_EOL . "    " . PATH::makeRelative($destination, $base_path) . PHP_EOL;
            echo $log;
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
}
