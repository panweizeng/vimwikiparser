<?php
require_once 'PHPUnit/Framework.php';
require_once '../VimwikiParser.php';


class VimwikiParserTest extends PHPUnit_Framework_TestCase
{
	private static $parser;

	private static function importConf()
	{
		require 'conf.php';
	}

	public function setUp()
	{
		self::$parser = new VimwikiParser();
		$this->parser = self::$parser;
	}

	public function testTypefaces()
	{
		$src = '*bold text*';
		$des = '<p><strong>bold text</strong></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '*粗体*';
		$des = '<p><strong>粗体</strong></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '_italic text_';
		$des = '<p><em>italic text</em></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '~~strikeout text~~';
		$des = '<p><del>strikeout text</del></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '`code (no syntax) text`';
		$des = '<p><code>code (no syntax) text</code></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = 'super^script^';
		$des = '<p>super<sup>script</sup></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = 'sub,,script,,';
		$des = '<p>sub<sub>script</sub></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testLinkImages()
	{
		$src = 'wikiWords:CapitalizedWordsConnected noWikiWords:!CapitalizedWordsConnected';
		$des = '<p>wikiWords:<a href="/wiki/CapitalizedWordsConnected">CapitalizedWordsConnected</a> noWikiWords:!CapitalizedWordsConnected</p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = 'http://code.google.com/p/vimwiki';
		$des = '<p><a href="http://code.google.com/p/vimwiki">http://code.google.com/p/vimwiki</a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://google.com][google]]';
		$des = '<p><a href="http://google.com">google</a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[http://habamax.ru/blog habamax home page]';
		$des = '<p><a href="http://habamax.ru/blog">habamax home page</a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = 'http://someaddr.com/picture.jpg';
		$des = '<p><img src="http://someaddr.com/picture.jpg" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[images/pabloymoira.jpg]]';
		$des = '<p><img src="images/pabloymoira.jpg" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg]]';
		$des = '<p><img src="http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg|dance]]';
		$des = '<p><a href="http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg">dance</a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg|dance|]]';
		$des = '<p><img src="http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg" alt="dance" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://helloworld.com/blabla.jpg|cool stuff|width:150px; height: 120px;]]';
		$des = '<p><img src="http://helloworld.com/blabla.jpg" alt="cool stuff" style="width:150px; height: 120px;" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://helloworld.com/blabla.jpg||width:150px; height: 120px;]]';
		$des = '<p><img src="http://helloworld.com/blabla.jpg" alt="" style="width:150px; height: 120px;" /></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[http://someaddr.com/bigpicture.jpg http://someaddr.com/thumbnail.jpg]';
		$des = '<p><a href="http://someaddr.com/bigpicture.jpg"><img src="http://someaddr.com/thumbnail.jpg" /></a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '[[http://someaddr.com/bigpicture.jpg|http://someaddr.com/thumbnail.jpg]]';
		$des = '<p><a href="http://someaddr.com/bigpicture.jpg"><img src="http://someaddr.com/thumbnail.jpg" /></a></p>';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testHeaders()
	{
		$src = '= Header level 1 =
== Header level 2 ==
=== Header level 3 ===
==== Header level 4 ====
===== Header level 5 =====
====== Header level 6 ======
';
		$des = '<h1 id="toc_1">Header level 1</h1>
<h2 id="toc_1.1">Header level 2</h2>
<h3 id="toc_1.1.1">Header level 3</h3>
<h4 id="toc_1.1.1.1">Header level 4</h4>
<h5 id="toc_1.1.1.1.1">Header level 5</h5>
<h6 id="toc_1.1.1.1.1.1">Header level 6</h6>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$this->parser->headers = array();
		$this->parser->toc = array();
		$src = '= Header level 1 =
== Header level 2 ==
== Header level 2  ==
=== Header level 3 ===
= Header level 1 ==
== Header level 2 ==
';
		$des = '<h1 id="toc_1">Header level 1</h1>
<h2 id="toc_1.1">Header level 2</h2>
<h2 id="toc_1.2">Header level 2</h2>
<h3 id="toc_1.2.1">Header level 3</h3>
<h1 id="toc_2">Header level 1</h1>
<h2 id="toc_2.1">Header level 2</h2>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testParagraghs()
	{
		$src = '
This is _first_ paragraph
with two lines.

This is a *second* paragraph with
two lines.
';
		$des = '
<p>This is <em>first</em> paragraph
with two lines.</p>

<p>This is a <strong>second</strong> paragraph with
two lines.</p>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testList()
	{
		$src = '
Unordered lists: 
  * Bulleted list item 1
  * Bulleted list item 2
    * Bulleted list sub item 1
    * Bulleted list sub item 2
    * more ...
      * and more ...
      * ...
    * Bulleted list sub item 3
    * etc.
';
		$des = '
<p>Unordered lists: </p>
<ul class="list list-0">
    <li>Bulleted list item 1</li>
    <li>Bulleted list item 2
    <ul class="list list-1">
        <li>Bulleted list sub item 1</li>
        <li>Bulleted list sub item 2</li>
        <li>more ...
        <ul class="list list-2">
            <li>and more ...</li>
            <li>...</li>
        </ul>
        </li>
        <li>Bulleted list sub item 3</li>
        <li>etc.</li>
    </ul>
    </li>
</ul>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		$src = '
  - Bulleted list item 1
  - Bulleted list item 2
    - Bulleted list sub item 1
    - Bulleted list sub item 2
    - more ...
      - and more ...
      - ...
    - Bulleted list sub item 3
    - etc.
';
		$des = '
<ul class="list list-0">
    <li>Bulleted list item 1</li>
    <li>Bulleted list item 2
    <ul class="list list-1">
        <li>Bulleted list sub item 1</li>
        <li>Bulleted list sub item 2</li>
        <li>more ...
        <ul class="list list-2">
            <li>and more ...</li>
            <li>...</li>
        </ul>
        </li>
        <li>Bulleted list sub item 3</li>
        <li>etc.</li>
    </ul>
    </li>
</ul>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		// mix
		$src = '
  - Bulleted list item 1
  - Bulleted list item 2
    * Bulleted list sub item 1
    * Bulleted list sub item 2
    * more ...
      - and more ...
      - ...
    * Bulleted list sub item 3
    * etc.
';
		$des = '
<ul class="list list-0">
    <li>Bulleted list item 1</li>
    <li>Bulleted list item 2
    <ul class="list list-1">
        <li>Bulleted list sub item 1</li>
        <li>Bulleted list sub item 2</li>
        <li>more ...
        <ul class="list list-2">
            <li>and more ...</li>
            <li>...</li>
        </ul>
        </li>
        <li>Bulleted list sub item 3</li>
        <li>etc.</li>
    </ul>
    </li>
</ul>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		// order list
		$src = '
Ordered lists: 
  # Numbered list item 1
  # Numbered list item 2
    # Numbered list sub item 1
    # Numbered list sub item 2
    # more ...
      # and more ...
      # ...
    # Numbered list sub item 3
    # etc.
';
		$des = '
<p>Ordered lists: </p>
<ol class="list list-0">
    <li>Numbered list item 1</li>
    <li>Numbered list item 2
    <ol class="list list-1">
        <li>Numbered list sub item 1</li>
        <li>Numbered list sub item 2</li>
        <li>more ...
        <ol class="list list-2">
            <li>and more ...</li>
            <li>...</li>
        </ol>
        </li>
        <li>Numbered list sub item 3</li>
        <li>etc.</li>
    </ol>
    </li>
</ol>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		// mix unordered list and ordered list
		$src = '
  * Bulleted list item 1
  * Bulleted list item 2
    # Numbered list sub item 1
    # Numbered list sub item 2
';
		$des = '
<ul class="list list-0">
    <li>Bulleted list item 1</li>
    <li>Bulleted list item 2
    <ol class="list list-1">
        <li>Numbered list sub item 1</li>
        <li>Numbered list sub item 2</li>
    </ol>
    </li>
</ul>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);

		// mutiple line
		$src = '
Multiline list items: 
  * Bulleted list item 1
    List item 1 continued line.
    List item 1 next continued line.
  * Bulleted list item 2
    * Bulleted list sub item 1
      List sub item 1 continued line.
      List sub item 1 next continued line.
    * Bulleted list sub item 2
    * etc.
';
		$des = '
<p>Multiline list items: </p>
<ul class="list list-0">
    <li>Bulleted list item 1List item 1 continued line.List item 1 next continued line.</li>
    <li>Bulleted list item 2
    <ul class="list list-1">
        <li>Bulleted list sub item 1List sub item 1 continued line.List sub item 1 next continued line.</li>
        <li>Bulleted list sub item 2</li>
        <li>etc.</li>
    </ul>
    </li>
</ul>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testDefination()
	{
		$src = '
Term 1::Definition 1
Term 2::Definition 2
';
		$des = '
<dl>
    <dt>Term 1</dt>
    <dd>Definition 1</dd>
    <dt>Term 2</dt>
    <dd>Definition 2</dd>
</dl>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testTable()
	{
		$src = '
 | Year | Temperature (low) | Temperature (high) |
 |------+-------------------+--------------------|
 | 1900 | -10               | 25                 |
 | 1910 | -15               | 30                 |
 | 1920 | -10               | 32                 |
 | 1930 | _N/A_             | _N/A_              |
 | 1940 | -2                | 40 [[http://google.com][google]]                |
';
		$des = '
<table class="center">
    <tr>
        <th>Year</th>
        <th>Temperature (low)</th>
        <th>Temperature (high)</th>
    </tr>
    <tr>
        <td>1900</td>
        <td>-10</td>
        <td>25</td>
    </tr>
    <tr>
        <td>1910</td>
        <td>-15</td>
        <td>30</td>
    </tr>
    <tr>
        <td>1920</td>
        <td>-10</td>
        <td>32</td>
    </tr>
    <tr>
        <td>1930</td>
        <td><em>N/A</em></td>
        <td><em>N/A</em></td>
    </tr>
    <tr>
        <td>1940</td>
        <td>-2</td>
        <td>40 <a href="http://google.com">google</a></td>
    </tr>
</table>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testPreformat()
	{
		$src = '
{{{ 
  Tyger! Tyger! burning bright
   In the forests of the night,
    What immortal hand or eye
     Could frame thy fearful symmetry?
  In what distant deeps or skies
   Burnt the fire of thine eyes?
    On what wings dare he aspire?
     What the hand dare sieze the *fire*?
}}}
';
		$des = '
<pre> 
  Tyger! Tyger! burning bright
   In the forests of the night,
    What immortal hand or eye
     Could frame thy fearful symmetry?
  In what distant deeps or skies
   Burnt the fire of thine eyes?
    On what wings dare he aspire?
     What the hand dare sieze the *fire*?
</pre>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
		
		$src = '
{{{class="brush: python"
 def hello(world):
     for x in range(10):
         print("Hello {0} number {1}".format(world, x))
}}}
';
		$des = '
<pre class="brush: python">
 def hello(world):
     for x in range(10):
         print("Hello {0} number {1}".format(world, x))
</pre>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}
	public function testBlockQuotes()
	{
		$src = '
Text started with 4 or more spaces is a blockquote.

    This would be a blockquote in vimwiki. It is not highlighted in vim but
    could be styled by css in html. Blockquotes are usually used to quote a
    long piece of text from another source.
';
		$des = '
<p>Text started with 4 or more spaces is a blockquote.</p>

<blockquote>This would be a blockquote in vimwiki. It is not highlighted in vim but
could be styled by css in html. Blockquotes are usually used to quote a
long piece of text from another source.</blockquote>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testComment()
	{
		$src = '<!-- this text would not be in HTML -->';
		$des = '';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}

	public function testToc()
	{
		$src = '
%toc
= 我的知识库 =
  * 我的紧急任务
== 安装==
=== 系统条件 ===
== 映射 ==
=== 全局映射===
';
		$des = '
<div class="toc">
<ul class="list list-0">
    <li><a href="#toc_1">我的知识库</a>
    <ul class="list list-1">
        <li>安装
        <ul class="list list-2">
            <li>系统条件</li>
        </ul>
        </li>
        <li>映射
        <ul class="list list-2">
            <li>全局映射</li>
        </ul>
        </li>
    </ul>
    </li>
</ul>
</div>
<h1 id="toc_1">我的知识库</h1>
<ul class="list list-0">
    <li>我的紧急任务</li>
</ul>
<h2 id="toc_1.1">安装</h2>
<h3 id="toc_1.1.1">系统条件</h3>
<h2 id="toc_1.2">映射</h2>
<h3 id="toc_1.2.1">全局映射</h3>
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}
	
	public function testNohtml()
	{
		$src = '
If you do not want a wiki page to be converted to html, place:

%nohtml

into it.
';
		$des = '
If you do not want a wiki page to be converted to html, place:

%nohtml

into it.
';
		$ret = $this->parser->transform($src);
		$this->assertEquals($des, $ret);
	}
}




