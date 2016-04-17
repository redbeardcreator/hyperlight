<?php

class HyperlightCompiledLanguage {
    private $_id;
    private $_info;
    private $_extensions;
    private $_states;
    private $_rules;
    private $_mappings;
    private $_caseInsensitive;
    private $_postProcessors = array();

    public function __construct($id, $info, $extensions, $states, $rules, $mappings, $caseInsensitive, $postProcessors) {
        $this->_id = $id;
        $this->_info = $info;
        $this->_extensions = $extensions;
        $this->_caseInsensitive = $caseInsensitive;
        $this->_states = $this->compileStates($states);
        $this->_rules = $this->compileRules($rules);
        $this->_mappings = $mappings;

        foreach ($postProcessors as $ppkey => $ppvalue)
            $this->_postProcessors[$ppkey] = HyperLanguage::compile($ppvalue);
    }

    public function id() {
        return $this->_id;
    }

    public function name() {
        return $this->_info[HyperLanguage::NAME];
    }

    public function authorName() {
        if (!array_key_exists(HyperLanguage::AUTHOR, $this->_info))
            return null;
        $author = $this->_info[HyperLanguage::AUTHOR];
        if (is_string($author))
            return $author;
        if (!array_key_exists(HyperLanguage::NAME, $author))
            return null;
        return $author[HyperLanguage::NAME];
    }

    public function authorWebsite() {
        if (!array_key_exists(HyperLanguage::AUTHOR, $this->_info) or
            !is_array($this->_info[HyperLanguage::AUTHOR]) or
            !array_key_exists(HyperLanguage::WEBSITE, $this->_info[HyperLanguage::AUTHOR]))
            return null;
        return $this->_info[HyperLanguage::AUTHOR][HyperLanguage::WEBSITE];
    }

    public function authorEmail() {
        if (!array_key_exists(HyperLanguage::AUTHOR, $this->_info) or
            !is_array($this->_info[HyperLanguage::AUTHOR]) or
            !array_key_exists(HyperLanguage::EMAIL, $this->_info[HyperLanguage::AUTHOR]))
            return null;
        return $this->_info[HyperLanguage::AUTHOR][HyperLanguage::EMAIL];
    }

    public function authorContact() {
        $email = $this->authorEmail();
        return $email !== null ? $email : $this->authorWebsite();
    }

    public function extensions() {
        return $this->_extensions;
    }

    public function state($stateName) {
        return $this->_states[$stateName];
    }

    public function rule($ruleName) {
        return $this->_rules[$ruleName];
    }

    public function className($state) {
        if (array_key_exists($state, $this->_mappings))
            return $this->_mappings[$state];
        else if (strstr($state, ' ') === false)
            // No mapping for state.
            return $state;
        else {
            // Try mapping parts of nested state name.
            $parts = explode(' ', $state);
            $ret = array();

            foreach ($parts as $part) {
                if (array_key_exists($part, $this->_mappings))
                    $ret[] = $this->_mappings[$part];
                else
                    $ret[] = $part;
            }

            return implode(' ', $ret);
        }
    }

    public function postProcessors() {
        return $this->_postProcessors;
    }

    private function compileStates($states) {
        $ret = array();

        foreach ($states as $name => $state) {
            $newstate = array();

            if (!is_array($state))
                $state = array($state);

            foreach ($state as $key => $elem) {
                if ($elem === null)
                    continue;
                if (is_string($key)) {
                    if (!is_array($elem))
                        $elem = array($elem);

                    foreach ($elem as $el2) {
                        if ($el2 === '')
                            $newstate[] = $key;
                        else
                            $newstate[] = "$key $el2";
                    }
                }
                else
                    $newstate[] = $elem;
            }

            $ret[$name] = $newstate;
        }

        return $ret;
    }

    private function compileRules($rules) {
        $tmp = array();

        // Preprocess keyword list and flatten nested lists:

        // End of regular expression matching keywords.
        $end = $this->_caseInsensitive ? ')\b/i' : ')\b/';

        foreach ($rules as $name => $rule) {
            if (is_array($rule)) {
                if (self::isAssocArray($rule)) {
                    // Array is a nested list of rules.
                    foreach ($rule as $key => $value) {
                        if (is_array($value))
                            // Array represents a list of keywords.
                            $value = '/\b(?:' . implode('|', $value) . $end;

                        if (!is_string($key) or strlen($key) === 0)
                            $tmp[$name] = $value;
                        else
                            $tmp["$name $key"] = $value;
                    }
                }
                else {
                    // Array represents a list of keywords.
                    $rule = '/\b(?:' . implode('|', $rule) . $end;
                    $tmp[$name] = $rule;
                }
            }
            else {
                $tmp[$name] = $rule;
            } // if (is_array($rule))
        } // foreach

        $ret = array();

        foreach ($this->_states as $name => $state) {
            $regex_rules = array();
            $regex_names = array();
            $nesting_rules = array();

            foreach ($state as $rule_name) {
                $rule = $tmp[$rule_name];
                if ($rule instanceof Rule)
                    $nesting_rules[$rule_name] = $rule;
                else {
                    $regex_rules[] = $rule;
                    $regex_names[] = $rule_name;
                }
            }

            $ret[$name] = array_merge(
                array(preg_merge('|', $regex_rules, $regex_names)),
                $nesting_rules
            );
        }

        return $ret;
    }

    private static function isAssocArray(array $array) {
        foreach($array as $key => $_)
            if (is_string($key))
                return true;
        return false;
    }
}
