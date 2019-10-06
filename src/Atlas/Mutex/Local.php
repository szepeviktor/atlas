<?php
/**
 * This file is part of the Atlas package
 * @license http://opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace DecodeLabs\Atlas\Mutex;

use DecodeLabs\Atlas\Mutex;
use DecodeLabs\Atlas\MutexTrait;

use DecodeLabs\Atlas\File\Local as LocalFile;

use DecodeLabs\Glitch\Inspectable;
use DecodeLabs\Glitch\Dumper\Entity;
use DecodeLabs\Glitch\Dumper\Inspector;

class Local implements Mutex, Inspectable
{
    use MutexTrait {
        MutexTrait::__construct as private __mutexConstruct;
    }

    protected $file;

    /**
     * Init with name and path
     */
    public function __construct(string $name, string $dir)
    {
        $this->__mutexConstruct($name);
        $this->file = new LocalFile($dir.'/'.$name.'.lock');
    }


    /**
     * Create file and lock it
     */
    protected function acquireLock(bool $blocking): bool
    {
        if ($this->file->exists()) {
            return false;
        }

        $this->file->open('c');
        return $this->file->lockExclusive(!$blocking);
    }

    /**
     * Release file and delete it
     */
    protected function releaseLock(): void
    {
        $this->file->unlock()->close()->delete();
    }


    /**
     * Inspect for Glitch
     */
    public function glitchInspect(Entity $entity, Inspector $inspector): void
    {
        $entity
            ->setProperties([
                '*name' => $inspector($this->name),
                '*file' => $inspector($this->file)
            ])
            ->setMetaList([
                'counter' => $inspector($this->counter),
                'locked' => $this->isLocked()
            ]);
    }
}
