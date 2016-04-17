<?php

/**
 * Raised when the grammar offers a rule that has not been defined.
 */
class NoMatchingRuleException extends Exception {
    /** @internal */
    public function __construct($states, $position, $code) {
        $state = array_pop($states);
        parent::__construct(
            "State '$state' has no matching rule at position $position:\n" .
            $this->errorSurrounding($code, $position)
        );
    }

    // Try to extract the location of the error more or less precisely.
    // Only used for a comprehensive display.
    private function errorSurrounding($code, $pos) {
        $size = 10;
        $begin = $pos < $size ? 0 : $pos - $size;
        $end = $pos + $size > strlen($code) ? strlen($code) : $pos + $size;
        $offs = $pos - $begin;
        return substr($code, $begin, $end - $begin) . "\n" . sprintf("%{$offs}s", '^');
    }
}
