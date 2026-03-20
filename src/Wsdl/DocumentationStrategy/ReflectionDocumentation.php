<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Documentation_Strategy;

use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use ReflectionClass;
use ReflectionProperty;
use function trim;
final class Reflection_Documentation implements Documentation_Strategy_Interface
{
    /**
     * @return string
     */
    public function get_property_documentation(ReflectionProperty $property)
    {
        return $this->parse_doc_comment($property->get_doc_comment());
    }
    /**
     * @return string
     */
    public function get_complex_type_documentation(ReflectionClass $class)
    {
        return $this->parse_doc_comment($class->get_doc_comment());
    }
    /**
     * @param string $docComment
     */
    private function parse_doc_comment(string|bool $doc_comment): string
    {
        $documentation = [];
        foreach (explode("\n", $doc_comment) as $i => $line) {
            if ($i === 0) {
                continue;
            }
            $line = trim((string) preg_replace('/\s*\*+/', '', $line));
            if (preg_match('/^(@[a-z]|\/)/i', $line)) {
                break;
            }
            // only include newlines if we've already got documentation
            if (!empty($documentation) || $line !== '') {
                $documentation[] = $line;
            }
        }
        return implode("\n", $documentation);
    }
}