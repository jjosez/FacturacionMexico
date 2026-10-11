<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Tests;

use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Cfdi\Customer\PaymentMethodResolver;
use PHPUnit\Framework\TestCase;

final class PaymentMethodResolverTest extends TestCase
{
    public function testUnpaidInvoiceUsesPpdAndUndefinedPaymentMethod(): void
    {
        $invoice = $this->createMock(FacturaCliente::class);
        $invoice->method('paid')->willReturn(false);
        $invoice->expects($this->never())->method('getPaymentMethod');

        $this->assertSame([
            'FormaPago' => '99',
            'MetodoPago' => 'PPD',
        ], PaymentMethodResolver::resolve($invoice));
    }

    public function testPaidInvoiceUsesPueAndSatPaymentCode(): void
    {
        $paymentMethod = $this->getMockBuilder(FormaPago::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $paymentMethod->clavesat = '01';

        $invoice = $this->createMock(FacturaCliente::class);
        $invoice->method('paid')->willReturn(true);
        $invoice->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->assertSame([
            'FormaPago' => '01',
            'MetodoPago' => 'PUE',
        ], PaymentMethodResolver::resolve($invoice));
    }

    public function testPaidInvoiceWithoutSatPaymentCodeReturnsEmptyCode(): void
    {
        $paymentMethod = $this->getMockBuilder(FormaPago::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $invoice = $this->createMock(FacturaCliente::class);
        $invoice->method('paid')->willReturn(true);
        $invoice->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->assertSame([
            'FormaPago' => '',
            'MetodoPago' => 'PUE',
        ], PaymentMethodResolver::resolve($invoice));
    }
}
