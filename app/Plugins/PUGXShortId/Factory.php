<?php

namespace App\Plugins\PUGXShortId;

use App\Plugins\PUGXShortId\Shortid;
use RandomLib\Factory as RandomLibFactory;
use RandomLib\Generator;

final class Factory
{
    /**
     * @var int
     */
    private $length = 7;

    /**
     * @var string
     */
    private $alphabet = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-';

    /**
     * @var RandomLibFactory
     */
    private static $factory;

    public function generate(int $length = null, ?string $alphabet = null, bool $readable = false): Shortid
    {
        $length = null === $length ? $this->length : $length;
        if (null === $alphabet && $readable) {
            $alphabet = Generator::EASY_TO_READ;
        }
        $alphabet = null === $alphabet ? $this->alphabet : $alphabet;
        $id = self::getFactory()->getMediumStrengthGenerator()->generateString($length, $alphabet);

        return new Shortid($id);
    }

    public function setAlphabet(string $alphabet): void
    {
        $this->checkAlphabet($alphabet, true);
        $this->alphabet = $alphabet;
    }

    public function setLength(int $length): void
    {
        $this->checkLength($length);
        $this->length = $length;
    }

    public function getAlphabet(): string
    {
        return $this->alphabet;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public static function getFactory(): RandomLibFactory
    {
        if (null === self::$factory) {
            self::$factory = new RandomLibFactory();
        }

        return self::$factory;
    }

    public function checkLength(int $length = null, bool $strict = false): void
    {
        if (null === $length && !$strict) {
            return;
        }
        if ($length < 2 || $length > 20) {
            throw new \InvalidArgumentException('Invalid length.');
        }
    }

    public function checkAlphabet(?string $alphabet = null, bool $strict = false): void
    {
        if (null === $alphabet && !$strict) {
            return;
        }
        $alphaLength = null === $alphabet ? 0 : \mb_strlen($alphabet, 'UTF-8');
        if (64 !== $alphaLength) {
            throw new \InvalidArgumentException(\sprintf('Invalid alphabet: %s (length: %u)', $alphabet, $alphaLength));
        }
    }
}
