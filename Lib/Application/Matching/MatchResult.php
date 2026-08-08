<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching;

use FacturaScripts\Dinamic\Model\Producto;

class MatchResult
{
    public const CONFIDENCE_EXACT = 1.0;
    public const CONFIDENCE_HIGH = 0.85;
    public const CONFIDENCE_MEDIUM = 0.70;
    public const CONFIDENCE_LOW = 0.50;
    public const CONFIDENCE_NONE = 0.0;

    public float $confidence;
    public ?string $referencia;
    public ?Producto $product;
    public string $matchMethod;
    public string $matchDescription;
    public bool $isLinked;
    public array $extraData;

    public function __construct(
        float $confidence,
        ?string $referencia,
        ?Producto $product,
        string $matchMethod,
        string $matchDescription = '',
        bool $isLinked = false,
        array $extraData = []
    ) {
        $this->confidence = $confidence;
        $this->referencia = $referencia;
        $this->product = $product;
        $this->matchMethod = $matchMethod;
        $this->matchDescription = $matchDescription;
        $this->isLinked = $isLinked;
        $this->extraData = $extraData;
    }

    public static function noMatch(): self
    {
        return new self(
            self::CONFIDENCE_NONE,
            null,
            null,
            'none',
            'Sin coincidencia'
        );
    }

    public static function exactMatch(Producto $product, string $method, bool $isLinked = false): self
    {
        $description = $isLinked ? 'Producto vinculado al proveedor' : 'Coincidencia exacta';

        return new self(
            self::CONFIDENCE_EXACT,
            $product->referencia,
            $product,
            $method,
            $description,
            $isLinked
        );
    }

    public static function highConfidence(Producto $product, string $method, string $description): self
    {
        return new self(
            self::CONFIDENCE_HIGH,
            $product->referencia,
            $product,
            $method,
            $description
        );
    }

    public static function suggestion(Producto $product, float $confidence, string $method, string $description): self
    {
        return new self(
            $confidence,
            $product->referencia,
            $product,
            $method,
            $description
        );
    }

    public function isUsable(): bool
    {
        return $this->confidence >= self::CONFIDENCE_MEDIUM && $this->product !== null;
    }

    public function isExact(): bool
    {
        return $this->confidence >= self::CONFIDENCE_EXACT;
    }

    public function isLinked(): bool
    {
        return $this->isLinked;
    }

    public function getConfidenceLabel(): string
    {
        if ($this->confidence >= self::CONFIDENCE_EXACT) {
            return 'exact';
        }
        if ($this->confidence >= self::CONFIDENCE_HIGH) {
            return 'high';
        }
        if ($this->confidence >= self::CONFIDENCE_MEDIUM) {
            return 'medium';
        }
        if ($this->confidence >= self::CONFIDENCE_LOW) {
            return 'low';
        }
        return 'none';
    }

    public function toArray(): array
    {
        return [
            'confidence' => $this->confidence,
            'confidenceLabel' => $this->getConfidenceLabel(),
            'referencia' => $this->referencia,
            'product' => $this->product?->toArray(),
            'matchMethod' => $this->matchMethod,
            'matchDescription' => $this->matchDescription,
            'isLinked' => $this->isLinked,
            'extraData' => $this->extraData,
        ];
    }
}
