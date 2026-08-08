<?php

namespace FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\Matchers;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Dinamic\Model\Producto;
use FacturaScripts\Dinamic\Model\Proveedor;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatcherInterface;
use FacturaScripts\Plugins\FacturacionMexico\Lib\Application\Matching\MatchResult;

class FuzzyDescriptionMatcher implements MatcherInterface
{
    private const MIN_CONFIDENCE = 0.70;
    private const FUZZY_THRESHOLD = 0.80;
    private const MAX_RESULTS = 20;

    public function getName(): string
    {
        return 'fuzzy_description';
    }

    public function getPriority(): int
    {
        return 4;
    }

    public function match(array $concepto, Proveedor $supplier): ?MatchResult
    {
        $descripcion = mb_strtolower(trim($concepto['Descripcion'] ?? ''), 'UTF-8');

        if (empty($descripcion) || mb_strlen($descripcion, 'UTF-8') < 5) {
            return null;
        }

        $products = $this->searchProducts($descripcion);

        if (empty($products)) {
            return null;
        }

        $bestMatch = null;
        $bestConfidence = 0;

        foreach ($products as $product) {
            $productDesc = mb_strtolower(trim($product['descripcion'] ?? ''), 'UTF-8');

            $similarity = $this->calculateSimilarity($descripcion, $productDesc);

            if ($similarity > $bestConfidence && $similarity >= self::MIN_CONFIDENCE) {
                $bestConfidence = $similarity;
                $bestMatch = $product;
            }
        }

        if ($bestMatch === null) {
            return null;
        }

        $productModel = new Producto();
        $productModel->load($bestMatch['referencia']);

        $isLinked = $this->isLinkedToSupplier($productModel->referencia, $supplier->codproveedor);

        return MatchResult::suggestion(
            $productModel,
            $bestConfidence,
            $this->getName(),
            sprintf('Similitud: %d%%', (int)($bestConfidence * 100))
        );
    }

    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1Norm = $this->normalizeString($str1);
        $str2Norm = $this->normalizeString($str2);

        if ($str1Norm === $str2Norm) {
            return 1.0;
        }

        $str1Len = mb_strlen($str1Norm, 'UTF-8');
        $str2Len = mb_strlen($str2Norm, 'UTF-8');

        if ($str1Len === 0 || $str2Len === 0) {
            return 0.0;
        }

        $levenshtein = levenshtein($str1Norm, $str2Norm);
        $maxLen = max($str1Len, $str2Len);

        $levSimilarity = 1 - ($levenshtein / $maxLen);

        similar_text($str1Norm, $str2Norm, $similarPercent);

        $similarity = ($levSimilarity * 0.6) + ($similarPercent / 100 * 0.4);

        return round($similarity, 2);
    }

    private function normalizeString(string $str): string
    {
        $str = preg_replace('/\s+/', ' ', $str);
        $str = trim($str);

        $translations = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N'
        ];

        return strtr($str, $translations);
    }

    private function searchProducts(string $query): array
    {
        $words = array_filter(explode(' ', $query), fn($w) => mb_strlen($w, 'UTF-8') >= 3);

        if (empty($words)) {
            return [];
        }

        $where = [];
        foreach ($words as $word) {
            $where[] = new DataBaseWhere('descripcion', '%' . $word . '%', 'LIKE');
        }

        $product = new Producto();
        $products = $product->all($where, [], 0, self::MAX_RESULTS);

        return array_map(fn($p) => $p->toArray(), $products);
    }

    private function isLinkedToSupplier(string $referencia, string $codproveedor): bool
    {
        $sql = "SELECT 1 FROM productos_proveedores WHERE referencia = ? AND codproveedor = ? LIMIT 1";

        $db = new \FacturaScripts\Core\Base\DataBase();
        $result = $db->select($sql, [$referencia, $codproveedor]);

        return !empty($result);
    }
}
