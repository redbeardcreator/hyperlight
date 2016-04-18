<?php

class Hyperlight {
    private $_lang;
    private $_result;
    private $_states;
    private $_omitSpans;
    private $_postProcessors = array();

    public function __construct($lang) {
        if (is_string($lang))
            $this->_lang = HyperLanguage::compileFromName(strtolower($lang));
        else if ($lang instanceof HyperlightCompiledLanguage)
            $this->_lang = $lang;
        else if ($lang instanceof HyperLanguage)
            $this->_lang = HyperLanguage::compile($lang);
        else
            trigger_error(
                'Invalid argument type for $lang to Hyperlight::__construct',
                E_USER_ERROR
            );

        foreach ($this->_lang->postProcessors() as $ppkey => $ppvalue)
            $this->_postProcessors[$ppkey] = new Hyperlight($ppvalue);

        $this->reset();
    }

    public function language() {
        return $this->_lang;
    }

    public function reset() {
        $this->_states = array('init');
        $this->_omitSpans = array();
    }

    public function render($code) {
        // Normalize line breaks.
        $this->_code = preg_replace('/\r\n?/', "\n", $code);
        $fm = hyperlight_calculate_fold_marks($this->_code, $this->language()->id());
        return hyperlight_apply_fold_marks($this->renderCode(), $fm);
    }

    public function renderAndPrint($code) {
        echo $this->render($code);
    }


    private function renderCode() {
        $code = $this->_code;
        $pos = 0;
        $len = strlen($code);
        $this->_result = '';
        $state = array_peek($this->_states);

        // If there are open states (reentrant parsing), open the corresponding
        // tags first:

        for ($i = 1; $i < count($this->_states); ++$i)
            if (!$this->_omitSpans[$i - 1]) {
                $class = $this->_lang->className($this->_states[$i]);
                $this->write("<span class=\"$class\">");
            }

        // Emergency break to catch faulty rules.
        $prev_pos = -1;

        while ($pos < $len) {
            // The token next to the current position, after the inner loop completes.
            // i.e. $closest_hit = array($matched_text, $position)
            $closest_hit = array('', $len);
            // The rule that found this token.
            $closest_rule = null;
            $rules = $this->_lang->rule($state);

            foreach ($rules as $name => $rule) {
                if ($rule instanceof Rule)
                    $this->matchIfCloser(
                        $rule->start(), $name, $pos, $closest_hit, $closest_rule
                    );
                else if (preg_match($rule, $code, $matches, PREG_OFFSET_CAPTURE, $pos) == 1) {
                    // Search which of the sub-patterns matched.

                    foreach ($matches as $group => $match) {
                        if (!is_string($group))
                            continue;
                        if ($match[1] !== -1) {
                            $closest_hit = $match;
                            $closest_rule = str_replace('_', ' ', $group);
                            break;
                        }
                    }
                }
            } // foreach ($rules)

            // If we're currently inside a rule, check whether we've come to the
            // end of it, or the end of any other rule we're nested in.

            if (count($this->_states) > 1) {
                $n = count($this->_states) - 1;
                do {
                    $rule = $this->_lang->rule($this->_states[$n - 1]);
                    $rule = $rule[$this->_states[$n]];
                    --$n;
                    if ($n < 0)
                        throw new NoMatchingRuleException($this->_states, $pos, $code);
                } while ($rule->end() === null);

                $this->matchIfCloser($rule->end(), $n + 1, $pos, $closest_hit, $closest_rule);
            }

            // We take the closest hit:

            if ($closest_hit[1] > $pos)
                $this->emit(substr($code, $pos, $closest_hit[1] - $pos));

            $prev_pos = $pos;
            $pos = $closest_hit[1] + strlen($closest_hit[0]);

            if ($prev_pos === $pos and is_string($closest_rule))
                if (array_key_exists($closest_rule, $this->_lang->rule($state))) {
                    array_push($this->_states, $closest_rule);
                    $state = $closest_rule;
                    $this->emitPartial('', $closest_rule);
                }

            if ($closest_hit[1] === $len)
                break;
            else if (!is_string($closest_rule)) {
                // Pop state.
                if (count($this->_states) <= $closest_rule)
                    throw new NoMatchingRuleException($this->_states, $pos, $code);

                while (count($this->_states) > $closest_rule + 1) {
                    $lastState = array_pop($this->_states);
                    $this->emitPop('');
                }
                $lastState = array_pop($this->_states);
                $state = array_peek($this->_states);
                $this->emitPop($closest_hit[0]);
            }
            else if (array_key_exists($closest_rule, $this->_lang->rule($state))) {
                // Push state.
                array_push($this->_states, $closest_rule);
                $state = $closest_rule;
                $this->emitPartial($closest_hit[0], $closest_rule);
            }
            else
                $this->emit($closest_hit[0], $closest_rule);
        }

        // Close any tags that are still open (can happen in incomplete code
        // fragments that don't necessarily signify an error (consider PHP
        // embedded in HTML, or a C++ preprocessor code not ending on newline).

        $omitSpansBackup = $this->_omitSpans;
        for ($i = count($this->_states); $i > 1; --$i)
            $this->emitPop();
        $this->_omitSpans = $omitSpansBackup;

        return $this->_result;
    }

    private function matchIfCloser($expr, $next, $pos, &$closest_hit, &$closest_rule) {
        $matches = array();
        if (preg_match($expr, $this->_code, $matches, PREG_OFFSET_CAPTURE, $pos) == 1) {
            if (
                (
                    // Two hits at same position -- compare length
                    // For equal lengths: first come, first serve.
                    $matches[0][1] == $closest_hit[1] and
                    strlen($matches[0][0]) > strlen($closest_hit[0])
                ) or
                $matches[0][1] < $closest_hit[1]
            ) {
                $closest_hit = $matches[0];
                $closest_rule = $next;
            }
        }
    }

    private function processToken($token) {
        if ($token === '')
            return '';
        $nest_lang = array_peek($this->_states);
        if (array_key_exists($nest_lang, $this->_postProcessors))
            return $this->_postProcessors[$nest_lang]->render($token);
        else
            return htmlspecialchars($token, ENT_NOQUOTES);
    }

    private function emit($token, $class = '') {
        $token = $this->processToken($token);
        if ($token === '')
            return;
        $class = $this->_lang->className($class);
        if ($class === '')
            $this->write($token);
        else
            $this->write("<span class=\"$class\">$token</span>");
    }

    private function emitPartial($token, $class) {
        $token = $this->processToken($token);
        $class = $this->_lang->className($class);
        if ($class === '') {
            if ($token !== '')
                $this->write($token);
            array_push($this->_omitSpans, true);
        }
        else {
            $this->write("<span class=\"$class\">$token");
            array_push($this->_omitSpans, false);
        }
    }

    private function emitPop($token = '') {
        $token = $this->processToken($token);
        if (array_pop($this->_omitSpans))
            $this->write($token);
        else
            $this->write("$token</span>");
    }

    private function write($text) {
        $this->_result .= $text;
    }
}
