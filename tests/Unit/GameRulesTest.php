<?php

namespace App\Tests\Unit;

use App\Service\Game;
use App\Service\Signature;
use App\Command\GenerateCodesCommand;
use PHPUnit\Framework\TestCase;

final class GameRulesTest extends TestCase
{
    public function testNormalizeRemovesSpacesAndUppercases(): void
    {
        self::assertSame('AB3K7Q9XMT', Game::normalize(' ab3k 7q9x-mt '));
    }

    public function testFormatRejectsAmbiguousCharacters(): void
    {
        self::assertTrue(Game::validFormat('AB3K7Q9XMT'));
        self::assertFalse(Game::validFormat('AB3K7Q9XM'));
        self::assertFalse(Game::validFormat('0B3K7Q9XMT'));
        self::assertFalse(Game::validFormat('IB3K7Q9XMT'));
    }

    public function testGeneratedCodesAreValid(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            self::assertTrue(Game::validFormat(GenerateCodesCommand::randomCode()));
        }
    }

    public function testDrawIsReproducible(): void
    {
        $a = Game::winnerIndex(str_repeat('a', 64), str_repeat('b', 64), 1000);
        self::assertSame($a, Game::winnerIndex(str_repeat('a', 64), str_repeat('b', 64), 1000));
        self::assertLessThan(1000, $a);
    }

    public function testSignatureDetectsTampering(): void
    {
        $s = Signature::sign('k', 'POST', '/api/v1/x', '1', 'n', '{}');
        self::assertTrue(Signature::verify('k', 'POST', '/api/v1/x', '1', 'n', '{}', $s));
        self::assertFalse(Signature::verify('k', 'POST', '/api/v1/x', '1', 'n', '{"a":1}', $s));
    }
}
