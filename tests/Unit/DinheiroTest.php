<?php

namespace Tests\Unit;

use App\Support\Dinheiro;
use PHPUnit\Framework\TestCase;

class DinheiroTest extends TestCase
{
    public function test_um_padrao_so_para_moeda(): void
    {
        $this->assertSame('R$ 1.250,00', Dinheiro::brl(1250));
        $this->assertSame('-R$ 98,15', Dinheiro::brl(-98.15));
        $this->assertSame('R$ 0,00', Dinheiro::brl(-0.001), 'zero não leva sinal');
        $this->assertSame('-R$ 1.234.567,89', Dinheiro::brl('-1234567.89'));
        $this->assertSame('—', Dinheiro::brl(null));
        $this->assertSame('R$ 0,30', Dinheiro::brl(0.1 + 0.2));
    }
}
