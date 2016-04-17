<?php

/*
 * Copyright 2008 Konrad Rudolph
 * All rights reserved.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

/*
 * TODO list
 * =========
 *
 * - FIXME Nested syntax elements create redundant nested tags under certain
 *   circumstances. This can be reproduced by the following PHP snippet:
 *
 *      <pre class="<?php echo; ? >">
 *
 *   (Remove space between `?` and `>`).
 *   Although this no longer occurs, it is fixed by checking for `$token === ''`
 *   in the `emit*` methods. This should never happen anyway. Probably something
 *   to do with the zero-width lookahead in the PHP syntax definition.
 *
 * - `hyperlight_calculate_fold_marks`: refactor, write proper handler
 *
 * - Line numbers (on client-side?)
 *
 */

/**
 * Hyperlight source code highlighter for PHP.
 * @package hyperlight
 */

require_once('vendor/autoload.php');

/**
 * <var>echo</var>s a highlighted code.
 *
 * For example, the following
 * <code>
 * hyperlight('<?php echo \'Hello, world\'; ?>', 'php');
 * </code>
 * results in:
 * <code>
 * <pre class="source-code php">...</pre>
 * </code>
 *
 * @param string $code The code.
 * @param string $lang The language of the code.
 * @param string $tag The surrounding tag to use. Optional.
 * @param array $attributes Attributes to decorate {@link $tag} with.
 *          If no tag is given, this argument can be passed in its place. This
 *          behaviour will be assumed if the third argument is an array.
 *          Attributes must be given as a hash of key value pairs.
 */
function hyperlight($code, $lang, $tag = 'pre', array $attributes = array()) {
    if ($code == '')
        die("`hyperlight` needs a code to work on!");
    if ($lang == '')
        die("`hyperlight` needs to know the code's language!");
    if (is_array($tag) and !empty($attributes))
        die("Can't pass array arguments for \$tag *and* \$attributes to `hyperlight`!");
    if ($tag == '')
        $tag = 'pre';
    if (is_array($tag)) {
        $attributes = $tag;
        $tag = 'pre';
    }
    $lang = htmlspecialchars(strtolower($lang));
    $class = "source-code $lang";

    $attr = array();
    foreach ($attributes as $key => $value) {
        if ($key == 'class')
            $class .= ' ' . htmlspecialchars($value);
        else
            $attr[] = htmlspecialchars($key) . '="' .
                      htmlspecialchars($value) . '"';
    }

    $attr = empty($attr) ? '' : ' ' . implode(' ', $attr);

    $hl = new Hyperlight($lang);
    echo "<$tag class=\"$class\"$attr>";
    $hl->renderAndPrint(trim($code));
    echo "</$tag>";
}

/**
 * Is the same as:
 * <code>
 * hyperlight(file_get_contents($filename), $lang, $tag, $attributes);
 * </code>
 * @see hyperlight()
 */
function hyperlight_file($filename, $lang = null, $tag = 'pre', array $attributes = array()) {
    if ($lang == '') {
        // Try to guess it from file extension.
        $pos = strrpos($filename, '.');
        if ($pos !== false) {
            $ext = substr($filename, $pos + 1);
            $lang = HyperLanguage::nameFromExt($ext);
        }
    }
    hyperlight(file_get_contents($filename), $lang, $tag, $attributes);
}

if (defined('HYPERLIGHT_SHORTCUT')) {
    function hy() {
        $args = func_get_args();
        call_user_func_array('hyperlight', $args);
    }
    function hyf() {
        $args = func_get_args();
        call_user_func_array('hyperlight_file', $args);
    }
}

function hyperlight_calculate_fold_marks($code, $lang) {
    $supporting_languages = array('csharp', 'vb');

    if (!in_array($lang, $supporting_languages))
        return array();

    $fold_begin_marks = array('/^\s*#Region/', '/^\s*#region/');
    $fold_end_marks = array('/^\s*#End Region/', '/\s*#endregion/');

    $lines = preg_split('/\r|\n|\r\n/', $code);

    $fold_begin = array();
    foreach ($fold_begin_marks as $fbm)
        $fold_begin = $fold_begin + preg_grep($fbm, $lines);

    $fold_end = array();
    foreach ($fold_end_marks as $fem)
        $fold_end = $fold_end + preg_grep($fem, $lines);

    if (count($fold_begin) !== count($fold_end) or count($fold_begin) === 0)
        return array();

    $fb = array();
    $fe = array();
    foreach ($fold_begin as $line => $_)
        $fb[] = $line;

    foreach ($fold_end as $line => $_)
        $fe[] = $line;

    $ret = array();
    for ($i = 0; $i < count($fb); $i++)
        $ret[$fb[$i]] = $fe[$i];

    return $ret;
}

function hyperlight_apply_fold_marks($code, array $fold_marks) {
    if ($fold_marks === null or count($fold_marks) === 0)
        return $code;

    $lines = explode("\n", $code);

    foreach ($fold_marks as $begin => $end) {
        $lines[$begin] = '<span class="fold-header">' . $lines[$begin] . '<span class="dots"> </span></span>';
        $lines[$begin + 1] = '<span class="fold">' . $lines[$begin + 1];
        $lines[$end + 1] = '</span>' . $lines[$end + 1];
    }

    return implode("\n", $lines);
}
