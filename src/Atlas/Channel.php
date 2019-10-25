<?php
/**
 * This file is part of the Atlas package
 * @license http://opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace DecodeLabs\Atlas;

use DecodeLabs\Atlas\DataProvider;
use DecodeLabs\Atlas\DataReceiver;
use DecodeLabs\Atlas\Channel;

interface Channel extends DataProvider, DataReceiver
{
    public function getResource();

    public function setBlocking(bool $flag): Channel;
    public function isBlocking(): bool;

    public function isReadable(): bool;
    public function read(int $length): ?string;
    public function readAll(): ?string;
    public function readChar(): ?string;
    public function readLine(): ?string;
    public function readTo(Channel $writer): Channel;

    public function isWritable(): bool;

    public function isAtEnd(): bool;
    public function close(): Channel;
}
