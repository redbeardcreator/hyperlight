# Hyperlight

This is a fork of Hyperlight. The intent is to make it installable via composer. Well, and also
modernize the codebase.

I'm doing this so I can propose using it in a few projects that I'm currently using that could use
PHP-based code highlighting.

## Why use Hyperlight?

* **Easy to use.** There’s no configuration. The following code will highlight your source
  code. Nothing more needs to be said or done.

  ```php
  <?php hyperlight('// Create a new hyperlight instance and print the highlighted code.
  $highlighter = new HyperLight($code, \'cpp\');
  $highlighter->theResult();', 'iphp'); ?>
  ```

  Even easier, there’s a handy function `hyperlight()` for lightweight usage, especially in
  HTML templates:

  ```php
  <?php hyperlight('<?php hyperlight($code, \'php\'); ?>', 'php'); ?>
  ```

  This code creates a <code>&lt;pre&gt;</code> container around the code. This can be controlled
  with a third argument to the function.

* **Easy to extend.** The syntax definitions are written in PHP but only very basic language grasp
  is needed. Syntax definitions are concise and for most tasks, existing templates can be used and
  it’s enough to customize a basic set of features.
* **Powerful.** The syntax definitions use regular expressions but they support stateful parsing
  through a very simple mechanism. This makes implementing context free grammars effortless.

* **Full CSS support.** One single CSS file can be used for all languages to give a consistent
  look & feel. Elements may be nested for refinements (e.g. highlighting “TODO” items in
  comments):

  ```php
  <?php hyperlight(".comment { color: gray; }
  .comment .todo { font-weight: bold; }", 'css'); ?>
  ```

  Further refinements are possible in order to differentiate similar elements. Consider the
  different classes of keywords:

  ```php
  <?php hyperlight(".keyword { color: #008; }
  .keyword.type { color: #088; }
  .keyword.operator { font-weight: bold; }", 'css'); ?>
  ```

* **Colour schemes.** This is basically the same as “full CSS support” but it sounds
  *waaay* cooler. Since CSS support is naturally included in Hyperlight
  and syntax files can define appropriate mappings for their lexemes, usage and creation of
  professional colour schemes is effortless.

## Why not use something else?

Sure, there are alternatives. Unfortunately, they are surprisingly few for PHP:

### Geshi

If you’re forced to work with PHP version < 5.0, sure, use Geshi. But be prepared that each syntax
brings its own (ugly) style, lacking conventions make the use of one CSS for all languages
impossible (because they use the same CSS class names for completely different things), a lot of
badly-documented configuration is necessary to get the desired result, HTML garbage is produced
and the CSS class names are gibberish.

Furthermore, many of the syntax definitions are badly realized and/or have bugs. Creating an own
highlighting isn't trivial because the API is quite complicated, not very powerful and lacks
documentation.

If that doesn't matter to you, Geshi is perhaps not such a bad choice.

### Pear_TextHighlighter

Syntax definitions must be given as cumbersome XML files. Need I say more?
