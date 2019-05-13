<?php
namespace IjorTengab\FilesBulkOperation\Action;

use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

class Reposition
{
    /**
     * The first two characters (from right) are about size.
     * The next two characters are about modified.
     * Equals means `11` and not equals means `00`.
     */
    const SIZE_LOWER      = 0b00000001;
    const SIZE_HIGHER     = 0b00000010;
    const SIZE_EQUALS     = 0b00000100;
    const SIZE_NOT_EQUALS = 0b00001000;
    const DATE_OLDER      = 0b00010000;
    const DATE_NEWER      = 0b00100000;
    const DATE_EQUALS     = 0b01000000;
    const DATE_NOT_EQUALS = 0b10000000;

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

    protected $override_policy = false;

    protected $override_callback = null;

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
    public function overridePolicy($bool, $callback = null)
    {
        $this->override_policy = $bool;
        if (null !== $callback) {
            $this->override_callback = $callback;
        }
        return $this;
    }

    /**
     *
     */
    public function printLog(&$log)
    {
        $lines = [];
        while ($line = array_shift($log)) {
            if (isset($line[0]) && isset($line[1])) {
                if (is_string($line[1])) {
                    $lines[] = str_repeat('  ', --$line[0]) . $line[1];
                }
                elseif (is_array($line[1]) && isset($line[1][0]) && isset($line[1][1])) {
                    $lines[] = str_repeat('  ', --$line[0]) . strtr($line[1][0], $line[1][1]);
                }
            }
        }
        echo implode(PHP_EOL, $lines) . PHP_EOL;
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

        $last_modified = $file->getMTime();
        $source = Path::join($this->working_directory_lookup, $file->getRelativePathname());
        $destination = Path::join($directory_destination, $file->getFilename());
        $base_path = PATH::getLongestCommonBasePath([$source, $destination]);
        $log[]  = [1, 'Moving file.'];
        $log[]  = [2, ['Common base path: %path', ['%path' => $base_path]]];
        $log[]  = [3, ['From : %path', ['%path' => PATH::makeRelative($source, $base_path)]]];
        $log[]  = [3, ['To   : %path', ['%path' => PATH::makeRelative($destination, $base_path)]]];
        $this->printLog($log);
        $rename = true;
        $override = false;
        if (file_exists($destination)) {
            $rename = false;
            $_log = [];
            $log_size = false;
            $log_modified = false;
            $_log[] = 'Destination has exists.';
            // Check size and modified.
            $info['source']['size'] = filesize($source);
            $info['source']['modified'] = filemtime($source);
            $info['destination']['size'] = filesize($destination);
            $info['destination']['modified'] = filemtime($destination);
            $source_condition = 0b000000;
            if ($info['source']['size'] < $info['destination']['size']) {
                $source_condition = $source_condition | static::SIZE_LOWER | static::SIZE_NOT_EQUALS;
                $_log[] = 'Source is lower than destination.';
                $log_size = true;
            }
            elseif ($info['source']['size'] > $info['destination']['size']) {
                $source_condition = $source_condition | static::SIZE_HIGHER | static::SIZE_NOT_EQUALS;
                $_log[] = 'Source is higher than destination.';
                $log_size = true;
            }
            else {
                $source_condition = $source_condition | static::SIZE_EQUALS;
                $_log[] = 'Size is equals.';
            }
            if ($info['source']['modified'] < $info['destination']['modified']) {
                $source_condition = $source_condition | static::DATE_OLDER | static::DATE_NOT_EQUALS;
                $_log[] = 'Source is older than destination.';
                $log_modified = true;
            }
            elseif ($info['source']['modified'] > $info['destination']['modified']) {
                $source_condition = $source_condition | static::DATE_NEWER | static::DATE_NOT_EQUALS;
                $_log[] = 'Source is newer than destination.';
                $log_modified = true;
            }
            else {
                $source_condition = $source_condition | static::DATE_EQUALS;
                $_log[] = 'Date modified is equals.';
            }
            $log[] = [2, implode(' ', $_log)];
            if ($log_size) {
                $log[]  = [3, ['Source size          : %size', ['%size' => $info['source']['size']]]];
                $log[]  = [3, ['Destination size     : %size', ['%size' => $info['destination']['size']]]];
            }
            if ($log_modified) {
                $log[]  = [3, ['Source modified      : %date', ['%date' => date('Y-m-d H:i:s', $info['source']['modified'])]]];
                $log[]  = [3, ['Destination modified : %date', ['%date' => date('Y-m-d H:i:s', $info['destination']['modified'])]]];
            }
            $this->printLog($log);
            // Check override.
            if ($this->override_policy) {
                $rename = true;
                $override = true;
                $log[] = [2, 'Override destination.'];
                if ($this->override_callback && is_callable($this->override_callback) ) {
                    $override_decision = call_user_func($this->override_callback, $source_condition);
                    if (true !== $override_decision) {
                        $rename = false;
                        $log[] = [3, 'Override terminated due the result of callback.'];
                    }
                    else {
                        $log[] = [3, 'Override with callback condition.'];
                    }
                }
                $this->printLog($log);
            }
        }
        if ($rename) {
            if ($this->dry_run === false) {
                $prepare_directory = Path::getDirectory($destination);
                $prepare = $this->filesystem->mkdir($prepare_directory);
                if ($override) {
                    $this->filesystem->rename($source, $destination, true);
                }
                else {
                    $this->filesystem->rename($source, $destination);
                }
                // Kejadian pada pindah partisi di Windows menggunakan
                // Cygwin. Dimana rename, tapi date modified mengalami
                // perubahan. Oleh karena itu, gunakan kembali touch.
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
}
