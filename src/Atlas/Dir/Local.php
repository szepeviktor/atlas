<?php
/**
 * This file is part of the Atlas package
 * @license http://opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace DecodeLabs\Atlas\Dir;

use DecodeLabs\Atlas\Node;
use DecodeLabs\Atlas\Node\LocalTrait;

use DecodeLabs\Atlas\File;
use DecodeLabs\Atlas\File\Local as LocalFile;
use DecodeLabs\Atlas\Dir;

use DecodeLabs\Atlas\Channel;
use DecodeLabs\Atlas\Channel\Stream;
use DecodeLabs\Atlas\Channel\Buffer;

use Generator;
use Traversable;
use DirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

use DecodeLabs\Glitch\Inspectable;
use DecodeLabs\Glitch\Dumper\Entity;
use DecodeLabs\Glitch\Dumper\Inspector;

class Local implements Dir, Inspectable
{
    use LocalTrait;
    use ScannerTrait;

    /**
     * Init with path
     */
    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
    }

    /**
     * Does this dir exist?
     */
    public function exists(): bool
    {
        return is_dir($this->path);
    }

    /**
     * Create dir if it doesn't exist
     */
    public function ensureExists(int $permissions=null): Dir
    {
        if (!is_dir($this->path)) {
            if (file_exists($this->path)) {
                throw Glitch::EIo('Dir destination exists as file', null, $this);
            }

            if ($permissions === null) {
                $permissions = 0777;
            }

            if (!mkdir($this->path, $permissions, true)) {
                throw Glitch::EIo('Unable to mkdir', null, $this);
            }
        } else {
            if ($permissions !== null) {
                chmod($this->path, $permissions);
            }
        }

        return $this;
    }

    /**
     * Does this dir contain anything?
     */
    public function isEmpty(): bool
    {
        if (!$this->exists()) {
            return true;
        }

        foreach (new \DirectoryIterator($this->path) as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isFile() || $item->isLink() || $item->isDir()) {
                return false;
            }
        }

        return true;
    }


    /**
     * Set permission on dir and children if $recursive
     */
    public function setPermissions(int $mode, bool $recursive=false): Dir
    {
        if (!$this->exists()) {
            throw Glitch::ENotFound('Cannot set permissions, dir does not exist', null, $this);
        }

        chmod($this->path, $mode);

        if ($recursive) {
            foreach ($this->scanRaw(true, true) as $item) {
                if ($item instanceof Dir) {
                    $item->setPermissions($mode, true);
                } else {
                    $item->setPermissions($mode);
                }
            }
        }

        return $this;
    }

    /**
     * Set owner on dir and children if $recursive
     */
    public function setOwner(int $owner, bool $recursive=false): Dir
    {
        if (!$this->exists()) {
            throw Glitch::ENotFound('Cannot set owner, dir does not exist', null, $this);
        }

        chown($this->path, $owner);

        if ($recursive) {
            foreach ($this->scanRaw(true, true) as $item) {
                if ($item instanceof Dir) {
                    $item->setOwner($owner, true);
                } else {
                    $item->setOwner($owner);
                }
            }
        }

        return $this;
    }

    /**
     * Set group on dir and children if $recursive
     */
    public function setGroup(int $group, bool $recursive=false): Dir
    {
        if (!$this->exists()) {
            throw Glitch::ENotFound('Cannot set group, dir does not exist', null, $this);
        }

        chgrp($this->path, $group);

        if ($recursive) {
            foreach ($this->scanRaw(true, true) as $item) {
                if ($item instanceof Dir) {
                    $item->setGroup($group, true);
                } else {
                    $item->setGroup($group);
                }
            }
        }

        return $this;
    }


    /**
     * Get iterator for flat Directory scanning
     */
    protected function getScannerIterator(bool $files, bool $dirs): Traversable
    {
        return new DirectoryIterator($this->path);
    }


    /**
     * Get iterator for recursive Directory scanning
     */
    protected function getRecursiveScannerIterator(bool $files, bool $dirs): Traversable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->path,
                FilesystemIterator::KEY_AS_PATHNAME |
                FilesystemIterator::CURRENT_AS_SELF |
                FilesystemIterator::SKIP_DOTS
            ),
            $dirs ?
                RecursiveIteratorIterator::SELF_FIRST :
                RecursiveIteratorIterator::LEAVES_ONLY
        );
    }



    /**
     * Get a child File or Dir if it exists
     */
    public function getChild(string $name): ?Node
    {
        $path = $this->path.'/'.ltrim($name, '/');

        if (is_dir($path)) {
            return new self($path);
        } elseif (is_file($path) || is_link($path)) {
            return new File($path);
        }

        return null;
    }

    /**
     * Is there an existing child by $name?
     */
    public function hasChild(string $name): bool
    {
        $path = $this->path.'/'.ltrim($name, '/');
        return file_exists($path);
    }

    /**
     * Ensure a child item is deleted
     */
    public function deleteChild(string $name): Node
    {
        if ($child = $this->getChild($child)) {
            $child->delete();
        }

        return $this;
    }


    /**
     * Create a dir as a child
     */
    public function createDir(string $name, int $permissions=null): Dir
    {
        return $this->getDir($name)->ensureExists($permissions);
    }

    /**
     * Does child dir exist?
     */
    public function hasDir(string $name): bool
    {
        return $this->getDir($name)->exists();
    }

    /**
     * Get a child dir
     */
    public function getDir(string $name, bool $ifExists=false): ?Dir
    {
        $output = new self($this->path.'/'.ltrim($name, '/'));

        if ($ifExists && !$output->exists()) {
            $output = null;
        }

        return $output;
    }

    /**
     * Delete child if its a dir
     */
    public function deleteDir(string $name): Dir
    {
        if ($dir = $this->getDir($name, true)) {
            $dir->delete();
        }

        return $this;
    }


    /**
     * Create a file with content
     */
    public function createFile(string $name, string $content): File
    {
        return $this->getFile($name)->putContents($content);
    }

    /**
     * Open a child file
     */
    public function openFile(string $name, string $mode): File
    {
        return $this->getFile($name)->open($mode);
    }

    /**
     * Does child file exist?
     */
    public function hasFile(string $name): bool
    {
        return $this->getFile($name)->exists();
    }

    /**
     * Get a child file
     */
    public function getFile(string $name, bool $ifExists=false): ?File
    {
        $output = $this->wrapFile($this->path.'/'.ltrim($name, '/'));

        if ($ifExists && !$output->exists()) {
            $output = null;
        }

        return $output;
    }

    /**
     * Delete child if its a file
     */
    public function deleteFile(string $name): Dir
    {
        if ($file = $this->getFile($name, true)) {
            $file->delete();
        }

        return $this;
    }


    /**
     * Copy dir to $destinationPath
     */
    public function copy(string $path): Node
    {
        if (file_exists($path)) {
            throw Glitch::EIo('Destination dir already exists', null, $this);
        }

        return $this->mergeInto($path);
    }

    /**
     * Move dir to $destinationDir, rename basename to $newName if set
     */
    public function move(string $path): Node
    {
        if (!$this->exists()) {
            throw Glitch::ENotFound('Source dir does not exist', null, $this);
        }

        (new Dir(dirname($path)))->ensureExists();

        if (file_exists($path)) {
            throw Glitch::EIo('Destination file already exists', null, $path);
        }

        if (!rename($this->path, $path)) {
            throw Glitch::EIo('Unable to rename dir', null, $this);
        }

        $this->path = $path;
        return $this;
    }


    /**
     * Recursively delete dir and its children
     */
    public function delete(): void
    {
        if (!$this->exists()) {
            return;
        }

        foreach ($this->scanRaw(true, true) as $item) {
            $item->delete();
        }

        rmdir($this->path);
    }

    /**
     * Recursively delete all children
     */
    public function emptyOut(): Dir
    {
        if (!$this->exists()) {
            return $this;
        }

        foreach ($this->scanRaw(true, true) as $item) {
            $item->delete();
        }

        return $this;
    }

    /**
     * Merge this dir and its contents into another dir
     */
    public function mergeInto(string $destination): Dir
    {
        if (!$this->exists()) {
            throw Glitch::ENotFound('Source dir does not exist', null, $this);
        }

        $destination = new self($destination);
        $destination->ensureExists($this->getPermissions());

        foreach ($this->scanRawRecursive(true, true) as $subPath => $item) {
            if ($item instanceof Dir) {
                $destination->createDir($subPath, $item->getPermissions());
            } else {
                $item->copyTo($destination->getPath().'/'.$subPath)
                    ->setPermissions($item->getPermissions());
            }
        }

        return $this;
    }


    /**
     * Wrap a file path into File object
     */
    protected function wrapFile(string $path): File
    {
        return new LocalFile($path);
    }


    /**
     * Inspect for Glitch
     */
    public function glitchInspect(Entity $entity, Inspector $inspector): void
    {
        $entity
            ->setDefinition($this->path)
            ->setMetaList([
                'exists' => $inspector($this->exists()),
                'permissions' => $this->getPermissionsOct(),
                'permissions' => $this->getPermissionsOct().' : '.$this->getPermissionsString()
            ]);
    }
}