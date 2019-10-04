<?php
/**
 * This file is part of the Atlas package
 * @license http://opensource.org/licenses/MIT
 */
declare(strict_types=1);
namespace DecodeLabs\Atlas;

interface Channel
{
    public function getResource();

    public function setBlocking(bool $flag): Channel;
    public function isBlocking(): bool;

    public function isReadable(): bool;
    public function read(int $length): ?string;
    public function readAll(): ?string;
    public function readLine(): ?string;
    public function writeTo(Channel $writer): Channel;

    public function isWritable(): bool;
    public function write(?string $data, int $length=null): int;
    public function writeLine(?string $data=''): int;
    public function writeBuffer(string &$buffer, int $length): int;

    public function isAtEnd(): bool;
    public function close(): Channel;
}