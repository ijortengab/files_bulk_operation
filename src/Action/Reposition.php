<?php
namespace IjorTengab\FilesBulkOperation\Action;

use Symfony\Component\Finder\Finder;
use Webmozart\PathUtil\Path;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

class Reposition
{
    /**
     * Binary flag to compared betwen source and destination. Relative to
     * source.
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
    protected $working_directory_lookup;

    /**
     * Deep level.
     */
    protected $working_directory_lookup_level;

    /**
     * Directory tempat menampung hasil reposition files.
     */
    protected $working_directory_destination;

    protected $filename_pattern;

    protected $directory_destination_pattern;

    protected $directory_destination_alt_pattern;

    protected $directory_destination_default ;

    protected $override_policy = false;

    protected $override_callback;

    protected $directory_listing;

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


        // Cek kondisi
        $_directory_destination_pattern = $this->directory_destination_pattern;
        $_directory_destination_alt_pattern = $this->directory_destination_alt_pattern;
        $_directory_destination_default = $this->directory_destination_default;
        // $debugname = '_directory_destination_pattern'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        // $debugname = '_directory_destination_alt_pattern'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
        // $debugname = '_directory_destination_default'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        if ($this->directory_destination_pattern !== null) {
            $directory_destination_pattern = preg_replace($this->filename_pattern, $this->directory_destination_pattern, $file->getFilename());
            // $debugname = 'directory_destination_pattern'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
            if ($this->directory_listing === null) {
                $dirs = Finder::create()
                    ->directories()
                    ->in($this->working_directory_destination);
                $this->directory_listing = iterator_to_array($dirs);
            }
            $matches = [];
            foreach ($this->directory_listing as $fullpath => $object) {
                $relativePath = $object->getRelativePathName();
                if (preg_match($directory_destination_pattern, $relativePath)) {
                    $matches[] = $relativePath;
                }
            }
            if (!empty($matches)) {
                if (count($matches) > 1) {
                    $log[] = [1, 'Warning.'];
                    $log[] = [2, ['Found directory destination more than one with pattern: %pattern. Using the first one.', ['%pattern' => $directory_destination_pattern]]];
                    foreach ($matches as $key => $match) {
                        $log[] = [3, ['%no : %direktori', ['%no' => $key + 1, '%direktori' => $match]]];
                    }
                    $this->printLog($log);
                }
                $directory_destination = Path::join($directory_destination, $matches[0]);
                $directory_destination_default = false;
            }
            elseif ($this->directory_destination_alt_pattern !== null) {
                $directory_destination_alt_pattern = true;
            }
        }
        if ($directory_destination_alt_pattern) {
            $directory_destination_alt_pattern = preg_replace($this->filename_pattern, $this->directory_destination_alt_pattern, $file->getFilename());
            $matches = [];
            foreach ($this->directory_listing as $fullpath => $object) {
                $relativePath = $object->getRelativePathName();
                if (preg_match($directory_destination_alt_pattern, $relativePath)) {
                    $matches[] = $relativePath;
                }
            }
            if (!empty($matches)) {
                if (count($matches) > 1) {
                    $log[] = [1, 'Warning.'];
                    $log[] = [2, ['Found directory destination more than one with pattern: %pattern. Using the first one.', ['%pattern' => $directory_destination_pattern]]];
                    foreach ($matches as $key => $match) {
                        $log[] = [3, ['%no : %direktori', ['%no' => $key + 1, '%direktori' => $match]]];
                    }
                    $this->printLog($log);
                }
                $directory_destination = Path::join($directory_destination, $matches[0]);
                $directory_destination_default = false;
            }
        }

        if ($this->directory_destination_default !== null && $directory_destination_default) {
            $directory_destination_default = preg_replace($this->filename_pattern, $this->directory_destination_default, $file->getFilename());
            $directory_destination = Path::join($directory_destination, $directory_destination_default);
        }
        $last_modified = $file->getMTime();
        $timezone = date_default_timezone_get();
        $lastModified = \DateTime::createFromFormat('U', $last_modified);
        $lastModified->setTimezone(new \DateTimeZone($timezone));
        $translate = [
            '${date:Y}' => $lastModified->format('Y'), // 2019
            '${date:y}' => $lastModified->format('y'), // 19
            '${date:m}' => $lastModified->format('m'), // 01-12
            '${date:n}' => $lastModified->format('n'), // 1-12
            '${date:d}' => $lastModified->format('d'), // 01-31
            '${date:j}' => $lastModified->format('j'), // 1-31
            '${date:g}' => $lastModified->format('g'), // 1-12
            '${date:G}' => $lastModified->format('G'), // 0-23
            '${date:h}' => $lastModified->format('h'), // 01-12
            '${date:H}' => $lastModified->format('H'), // 00-23
            '${date:i}' => $lastModified->format('i'), // 00-59
            '${date:s}' => $lastModified->format('s'), // 00-59
        ];
        $directory_destination = strtr($directory_destination, $translate);
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
                        $log[] = [3, 'Override terminated due the callback decision.'];
                    }
                    else {
                        $log[] = [3, 'Override with callback decision.'];
                    }
                }
                $this->printLog($log);
            }
        }
        if ($rename) {
            if ($this->dry_run === false) {
                $prepare_directory = Path::getDirectory($destination);
                $prepare = $this->filesystem->mkdir($prepare_directory);
                try {
                    if ($override) {
                        $this->filesystem->rename($source, $destination, true);
                    }
                    else {
                        $this->filesystem->rename($source, $destination);
                    }
                    $log[] = [2, 'Moving success.'];
                    // Kejadian pada pindah partisi di Windows menggunakan
                    // Cygwin. Dimana rename, tapi date modified mengalami
                    // perubahan. Oleh karena itu, gunakan kembali touch.
                    // Clear terlebih dahulu.
                    clearstatcache(true, $destination);
                    $current_modified = filemtime($destination);
                    if ($last_modified != $current_modified) {
                        $log[] = [3, ['Terjadi Perubahan modified menjadi: %date.', ['%date' => date('Y-m-d H:i:s', $current_modified)]]];
                        $result = @touch($destination, $last_modified);
                        if (false === $result) {
                            $log[] = [3, ['Gagal mengembalikan modified menjadi semula, yakni: %date.', ['%date' => date('Y-m-d H:i:s', $last_modified)]]];
                        }
                        else {
                            $log[] = [3, ['Berhasil mengembalikan modified menjadi: %date.', ['%date' => date('Y-m-d H:i:s', $last_modified)]]];
                        }
                    }
                }
                catch (IOException $e) {
                    $log[] = [2, 'Moving failed.'];
                    $log[] = [3, ['Error from Exception : %msg', ['%msg' => $e->getMessage()]]];
                }
                $this->printLog($log);
            }
        }
    }
}
