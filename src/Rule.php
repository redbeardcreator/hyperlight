<?php

/**
 * Represents a nesting rule in the grammar of a language definition.
 *
 * Individual rules can either be represented by raw strings ("simple" rules) or
 * by a nesting rule. Nesting rules specify where they can start and end. Inside
 * a nesting rule, other rules may be applied (both simple and nesting).
 * For example, a nesting rule may define a string literal. Inside that string,
 * other rules may be applied that recognize escape sequences.
 *
 * To use a nesting rule, supply how it may start and end, e.g.:
 * <code>
 * $string_rule = array('string' => new Rule('/"/', '/"/'));
 * </code>
 * You also need to specify nested states:
 * <code>
 * $string_states = array('string' => 'escaped');
 * <code>
 * Now you can add another rule for <var>escaped</var>:
 * <code>
 * $escaped_rule = array('escaped' => '/\\(x\d{1,4}|.)/');
 * </code>
 */
class Rule {
    /**
     * Common rules.
     */

    const ALL_WHITESPACE = '/(\s|\r|\n)+/';
    const C_IDENTIFIER = '/[a-z_][a-z0-9_]*/i';
    const C_COMMENT = '#//.*?\n|/\*.*?\*/#s';
    const C_MULTILINECOMMENT = '#/\*.*?\*/#s';
    const DOUBLEQUOTESTRING = '/"(?:\\\\"|.)*?"/s';
    const SINGLEQUOTESTRING = "/'(?:\\\\'|.)*?'/s";
    const C_DOUBLEQUOTESTRING = '/L?"(?:\\\\"|.)*?"/s';
    const C_SINGLEQUOTESTRING = "/L?'(?:\\\\'|.)*?'/s";
    const STRING = '/"(?:\\\\"|.)*?"|\'(?:\\\\\'|.)*?\'/s';
    const C_NUMBER = '/
        (?: # Integer followed by optional fractional part.
            (?:
                0(?:
                    x[0-9a-f]+
                    |
                    [0-7]*
                )
                |
                \d+
            )
            (?:\.\d*)?
            (?:e[+-]\d+)?
        )
        |
        (?: # Just the fractional part.
            (?:\.\d+)
            (?:e[+-]?\d+)?
        )
        /ix';

    private $_start;
    private $_end;

    /** @ignore */
    public function __construct($start, $end = null) {
        $this->_start = $start;
        $this->_end = $end;
    }

    /**
     * Returns the pattern with which this rule starts.
     * @return string
     */
    public function start() {
        return $this->_start;
    }

    /**
     * Returns the pattern with which this rule may end.
     * @return string
     */
    public function end() {
        return $this->_end;
    }
}
