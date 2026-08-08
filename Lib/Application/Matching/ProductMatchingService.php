<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching;

use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatcherInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers\ReferenceMatcher;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers\SupplierLinkMatcher;

class ProductMatchingService
{
    /**
     * @var MatcherInterface[]
     */
    private array $matchers = [];

    private bool $autoRegisterDefaultMatchers = true;

    public function __construct()
    {
    }

    public function registerMatcher(MatcherInterface $matcher): void
    {
        $this->matchers[$matcher->getName()] = $matcher;
        $this->autoRegisterDefaultMatchers = false;
    }

    public function getMatchers(): array
    {
        if ($this->autoRegisterDefaultMatchers && empty($this->matchers)) {
            $this->registerDefaultMatchers();
        }

        usort($this->matchers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

        return $this->matchers;
    }

    public function matchConcept(array $concepto, Proveedor $supplier): MatchResult
    {
        foreach ($this->getMatchers() as $matcher) {
            $result = $matcher->match($concepto, $supplier);

            if ($result !== null && $result->isUsable()) {
                return $result;
            }
        }

        return MatchResult::noMatch();
    }

    public function matchAll(array $conceptos, Proveedor $supplier): array
    {
        $results = [];

        foreach ($conceptos as $index => $concepto) {
            $results[$index] = $this->matchConcept($concepto, $supplier);
        }

        return $results;
    }

    public function getStats(array $results): MatchingStats
    {
        $stats = new MatchingStats();

        foreach ($results as $result) {
            $stats->total++;

            if ($result->isExact() && $result->isLinked()) {
                $stats->exactLinked++;
            } elseif ($result->isExact()) {
                $stats->exactUnlinked++;
            } elseif ($result->isUsable()) {
                $stats->suggestions++;
            } else {
                $stats->unmatched++;
            }
        }

        return $stats;
    }

    public function getBestMatch(array $conceptos, Proveedor $supplier): ?MatchResult
    {
        $bestResult = null;

        foreach ($conceptos as $concepto) {
            $result = $this->matchConcept($concepto, $supplier);

            if ($result->isExact() && $result->isLinked()) {
                return $result;
            }

            if ($result->isUsable() && ($bestResult === null || $result->confidence > $bestResult->confidence)) {
                $bestResult = $result;
            }
        }

        return $bestResult;
    }

    private function registerDefaultMatchers(): void
    {
        $this->matchers[] = new SupplierLinkMatcher();
        $this->matchers[] = new ReferenceMatcher();

        $this->autoRegisterDefaultMatchers = false;
    }
}

class MatchingStats
{
    public int $total = 0;
    public int $exactLinked = 0;
    public int $exactUnlinked = 0;
    public int $suggestions = 0;
    public int $unmatched = 0;

    public function getMatchRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return ($this->exactLinked + $this->exactUnlinked) / $this->total;
    }

    public function getAutoMatchRate(): float
    {
        if ($this->total === 0) {
            return 0.0;
        }

        return ($this->exactLinked + $this->suggestions) / $this->total;
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'exactLinked' => $this->exactLinked,
            'exactUnlinked' => $this->exactUnlinked,
            'suggestions' => $this->suggestions,
            'unmatched' => $this->unmatched,
            'matchRate' => $this->getMatchRate(),
            'autoMatchRate' => $this->getAutoMatchRate(),
        ];
    }
}
