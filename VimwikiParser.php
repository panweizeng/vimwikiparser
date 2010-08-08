<?php


class VimwikiParser
{
	const PATTERN_IMG = "/.(jpg|jpeg|gif|png)$/i";
	const PATTERN_WORLD = '\w\*\^\[\,~`\x{80}-\x{FF}';
	//const PATTERN_WORLD_BLOCKQUOTE = '\w\s\x{80}-\x{FF}';

	const LIST_TYPE_ORDER = 'order';
	const LIST_TYPE_UNORDER = 'unorder';

	const TABLE_TYPE_HEAD = 'th';
	const TABLE_TYPE_DATA = 'td';

	// change to \t if you don't like spaces
	const FORMAT_TAB = "    ";

	const PLACEHOLD_PRE = 'PREFORMAT_TEXT';
	const PLACEHOLD_NOHTML = '%nohtml';

	public $inlines = array(
		'bold',	
		'italic',
		'strike',
		'super',
		'sub',
		'code',
		'link',
		'wikiWord',
	);

	public $blocks = array(
		'header', 
		'pre',
		#'horizontalRule', 
		'list', 
		'definition', 
		'paragraph', 
		'blockQuote',
		'table',
	);

	public $placeholds = array(
		'toc',
	);

	// store table of content
	public $toc = array();
	public $headers = array(
		'h1' => array(),
		'h2' => array(),
		'h3' => array(),
		'h4' => array(),
		'h5' => array(),
		'h6' => array(),
	);
	public $pres = array();

	public function __construct($config = array())
	{
		$conf = array('baseUrl' => '/wiki');
		$this->config = array_merge($conf, $config);
	}

	/**
	 * 
	 */
	public function isImage($text)
	{
		return preg_match(self::PATTERN_IMG, $text);
	}

	/**
	 * log $val in firebug console
	 */
	public function log($val)
	{
		echo '<script>if(console){console.info(',json_encode($val),');}</script>';
	}

	public function transform($text)
	{
		if(strpos($text, self::PLACEHOLD_NOHTML) === false){
			$text = $this->setUp($text);
			$text = $this->makeBlocks($text);
			$text = $this->makeInlines($text);
			$text = $this->makePlaceholds($text);
			$text = $this->tearDown($text);
		}

		return $text;
	}

	public function setUp($text)
	{
		$text = $this->removeComment($text);

		$text = preg_replace('{\r\n?}', "\n", $text);
		$text = preg_replace("/\t/", self::FORMAT_TAB, $text);

		$text = str_replace('&', '&amp;', $text);
		$text = str_replace('<', '&lt;', $text);
		$text = str_replace('>', '&gt;', $text);

		return $text;
	}

	public function tearDown($text)
	{
		$text = $this->addPre($text);
		return $text;
	}

	public function addPre($text)
	{
		$lines = explode("\n", $text);
		$pattern = "/.*".self::PLACEHOLD_PRE.".*/";
		$text = preg_replace_callback($pattern, array(&$this, 'addPreCallback'), $text);

		return $text;
	}

	public function addPreCallback($matches)
	{
		$pre = array_shift($this->pres);	
		if(isset($pre)){
			return $pre;
		} else {
			return '';
		}
	}

	public function removeComment($text)
	{
		$pattern = "/<!--.*?-->/ms";
		$text = preg_replace($pattern, '', $text);
		return $text;
	}

	public function makeBlocks($text) 
	{
		foreach ($this->blocks as $method) {
			$method = 'make'.ucfirst($method);
			$text = $this->$method($text);
		}

		return $text;
	}

	public function makeInlines($text) 
	{
		foreach ($this->inlines as $method) {
			$method = 'make'.ucfirst($method);
			$text = $this->$method($text);
		}

		return $text;
	}

	public function makePlaceholds($text) 
	{
		foreach ($this->placeholds as $method) {
			$method = 'make'.ucfirst($method);
			$text = $this->$method($text);
		}

		return $text;
	}

	/**
	 * = Header1 =
	 */
	public function makeHeader($text) 
	{
		$pattern = '/(={1,6})\s*(.+?)\s*={1,6}/';
		$text = preg_replace_callback($pattern, array(&$this, 'makeHeaderCallback'), $text);

		return $text;
	}

	public function makeHeaderCallback($matches) 
	{
		$level = $rlevel = strlen($matches[1]);
		$tagName = 'h'.$level;
		$text = $matches[2];
		$arr = array();

		$item = new stdClass();
		$item->level = $level;
		$item->type = $tagName;
		$item->content = $text;
		$item->children = array();

		// store in the headers array
		$this->headers[$tagName][] = $text;
		// get level squence
		while($level > 0){
			$arr[] = count($this->headers['h'.$level--]);
		}
		// reset the sublevel
		while($rlevel < 6){
			$this->headers['h' . ++$rlevel] = array();	
		}
		$arr = array_reverse($arr);
		$id = 'toc_'.implode('.', $arr);
		$item->key = $id;
		$this->toc[] = $item;
		$block = '<'.$tagName.' id="'.$id.'">'.$text.'</'.$tagName.'>';
		return $block;
	}

	public function makeToc($text)
	{
		$pattern = "/%toc\s?([".self::PATTERN_WORLD."\s]*)/";
		$text = preg_replace_callback($pattern, array(&$this, 'makeTocCallback'), $text);
		return $text;
	}

	public function makeTocCallback($matches)
	{
		$caption = trim($matches[1]);
		if(empty($caption)){
			$replace = "<div class=\"toc\">\n".$this->getToc()."\n</div>\n";
		} else {
			$replace = "<h1>".$caption."</h1>\n<div class=\"toc\">\n".$this->getToc()."\n</div>\n";
		}
		return $replace;
	}

	public function getToc()
	{
		$toc = array();
		// clone $this->toc, because all %toc placeholds share this object array.
		foreach($this->toc as $val){
			$toc[] = clone $val;
		}
		$tree = $this->buildTocTree($toc);
		return $this->traverseTocTree($tree);
	}

	public function buildTocTree($arr)
	{
		$count = count($arr);
		if(!$count) return array();

		$first = $arr[0];
		$entryLevel = $first->level;
		$tree = new stdClass();
		$tree->type = null;
		$tree->content = null;
		$tree->parent = null;
		$tree->key = 'root';
		$tree->level = $entryLevel - 1;
		$tree->children = array();
		$lastNode = $tree;
		// store the relations
		$parentList = array();
		foreach($arr as $k => $val){
			$level = $val->level;
			$lastLevel = $lastNode->level;
			if($level > $lastLevel){
				$lastNode->children[] = $val;
				$parentList[$val->key] = $lastNode;
				$lastNode = $val;
			} elseif($level < $lastLevel) { 
				// look for the node has same level 
				while($level < $lastNode->level){
					$lastNode = $parentList[$lastNode->key];
				}
				$parent = $parentList[$lastNode->key];
				$parent->children[] = $val;
				$parentList[$val->key] = $parent;
				$lastNode = $val;
			} else { // $level == $lastLevel
				$parent = $parentList[$lastNode->key];
				$parent->children[] = $val;
				$parentList[$val->key] = $parent;
				$lastNode = $val;
			}
		}
		return $tree;
	}

	public function traverseTocTree($tree)
	{
		if(!count($tree->children)) return '';
		$ret = array();
		$ret[] = str_repeat(self::FORMAT_TAB, $tree->level);
		$ret[] = '<ul class="list list-'.$tree->level.'"' . ">\n";
		foreach($tree->children as $val){
			$hasChildren = count($val->children) > 0;
			$ret[] = str_repeat(self::FORMAT_TAB, $val->level) ;
			$ret[] = '<li><a href="#'.$val->key.'">'. $val->content.'' . "</a>";
			if($hasChildren){
				$ret[] = "\n";
				$ret[] = $this->traverseTree($val) . "";
				$ret[] = str_repeat(self::FORMAT_TAB, $val->level);
			}
			$ret[] = '</li>' . "\n";
		}
		$ret[] = str_repeat(self::FORMAT_TAB, $tree->level);
		$ret[] = '</ul>';
		if($tree->level > 0){
			$ret[] = "\n";
		}
		return implode('', $ret);
	}

	public function makeBold($text) 
	{
		$pattern = "/\*([^\*\n]+?)\*/i";
		$replace = "<strong>\${1}</strong>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeItalic($text) 
	{
		$pattern = "/_([^_\<\>\n]+?)_/i";
		$replace = "<em>\${1}</em>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeStrike($text) 
	{
		$pattern = "/~{2}([^~\n]+)~{2}/i";
		$replace = "<del>\${1}</del>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeSuper($text) 
	{
		$pattern = "/\^([^\^\n]+)\^/i";
		$replace = "<sup>\${1}</sup>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeSub($text) 
	{
		$pattern = "/\,{2}([^\,\n]+)\,{2}/i";
		$replace = "<sub>\${1}</sub>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeCode($text) 
	{
		$pattern = "/`([^`\n]+)`/i";
		$replace = "<code>\${1}</code>";
		$text = preg_replace($pattern, $replace, $text);
		return $text;
	}

	public function makeLink($text) 
	{
		$pattern = "/(http:\/\/[\w-]+\.[\w-\.\/]+)|(\[(.+)\])/i";
		$text = preg_replace_callback($pattern, array(&$this, 'makeLinkImageCallback'), $text);
		return $text;
	}

	public function makeLinkImageCallback($matches)
	{
		$replace = '';
		if(!empty($matches[1])){
			// http://code.google.com/p/vimwiki
			// http://someaddr.com/picture.jpg
			$link = $matches[1];
			if($this->isImage($link)){
				$replace =  '<img src="' . $link. '" />';
			} else {
				$replace =  '<a href="' . $link . '">' . $link . '</a>';
			}
		} else {
			// [http://habamax.ru/blog habamax home page]
			// [[images/pabloymoira.jpg]]
			$pattern = "/^\[(.+)\]$/i";
			$patternLinkText = "/^\[(.+)\]\[(.+)\]$/i";
			$str = $matches[3];
			$replace = '';
			// [[.*]]
			if(preg_match($patternLinkText, $str, $matches)){ // double []
				$replace = '<a href="' . $matches[1] . '">' . $matches[2] . '</a>';
			} elseif(preg_match($pattern, $str, $matches)){ // double []
				$tmp = explode('|', $matches[1]);
				$count = count($tmp);
				// [[http://helloworld.com/blabla.jpg|cool stuff|width:150px; height: 120px;]]
				if($count > 2){
					$replace = '<img src="' . $tmp[0]. '" alt="'.$tmp[1].'"';
					if(!empty($tmp[2])){
						$replace .= ' style="'.$tmp[2].'"';
					}
					$replace .= ' />';
				// [[http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg|dance]]
				// [[http://someaddr.com/bigpicture.jpg|http://someaddr.com/thumbnail.jpg]]
				} elseif($count > 1){
					if($this->isImage($tmp[1])){
						$replace = '<a href="' . $tmp[0] . '"><img src="' . $tmp[1] . '" /></a>';
					} else {
						$replace = '<a href="' . $tmp[0] . '">' . $tmp[1] . '</a>';
					}
				// [[http://habamax.ru/blog/wp-content/uploads/2009/01/2740254sm.jpg]]
				// [[http://ww-google.com/abc.jpg http://ww-google.com/thumb.jpg]]
				} else {
					$tmp = explode(' ', $tmp[0]);
					if(count($tmp) > 1) {
						if($this->isImage($tmp[1])){
							$replace = '<a href="' . $tmp[0] . '"><img src="' . $tmp[1] . '" /></a>';
						} else {
							$replace = '<a href="' . $tmp[0] . '">' . $tmp[1] . '</a>';
						}
					} else {
						if($this->isImage($tmp[0])){
							$replace = '<img src="' . $tmp[0]. '" />';
						} else {
							$replace = '<a href="' . $tmp[0] . '">' . $tmp[0] . '</a>';
						}
					}
				}
			} else { // single []
				$tmp = explode(' ', $str);
				if(count($tmp) > 1){
					// [http://someaddr.com/bigpicture.jpg http://someaddr.com/thumbnail.jpg]
					if($this->isImage($tmp[1])){
						$replace = '<a href="' . $tmp[0]. '"><img src="' . $tmp[1]. '" /></a>';
					// [http://habamax.ru/blog habamax home page]
					} else {
						$link = array_shift($tmp);
						$text = implode(' ', $tmp);
						$replace = '<a href="' . $link. '">' . $text. '</a>';
					}
				// [http://habamax.ru/blog]
				} else {
					$replace = '<a href="' . $tmp[0]. '">' . $tmp[0]. '</a>';
				}
			}
		}
		return $replace;
	}

	public function makeWikiWord($text) 
	{
		$pattern = '/(\!?)\b([A-Z]{1}[a-z]+[A-Z]{1}[a-zA-Z]+)/';
		$text = preg_replace_callback($pattern, array(&$this, 'makeWikiWordCallback'), $text);
		return $text;
	}

	public function makeWikiWordCallback($matches)
	{
		if(empty($matches[1])){
			return '<a href="'.$this->getWikiWordLink($matches[0]).'">'.$matches[0].'</a>';
		} else {
			return $matches[0];
		}
	}

	public function getWikiWordLink($word)
	{
		return rtrim($this->config['baseUrl'], '/').'/'.$word;
	}

	public function makePre($text)
	{
		$pattern = "/\{{3}(class=\"[^\"]+\")?(.+?)\}{3}/sm";
		$text = preg_replace_callback($pattern, array(&$this, 'makePreCallback'), $text);
		return $text;
	}

	public function makePreCallback($matches)
	{
		if(empty($matches[1])){
			$this->pres[] = '<pre>'.$matches[2].'</pre>';
		} else {
			$this->pres[] = '<pre '.$matches[1].'>'.$matches[2].'</pre>';
		}
		return self::PLACEHOLD_PRE;
	}

	public function makeParagraph($text)
	{
		$pattern = "/^[".self::PATTERN_WORLD."].+/";
		$lines = explode("\n", $text);
		$replaces = $matches = array();
		foreach($lines as $line){
			if(preg_match($pattern, $line)){
				$matches[] = $line;
			} else {
				if(count($matches)){
					$replaces[] = '<p>'. implode("\n", $matches) . '</p>';
					$matches = array();
				}
				$replaces[] = $line;
			}
		}
		if(count($matches)){
			$replaces[] = '<p>'. implode("\n", $matches) . '</p>';
		}

		$text = implode("\n", $replaces);
		return $text;
	}
	
	public function makeBlockquote($text)
	{
		$pattern = "/^\s{4,}([".self::PATTERN_WORLD."].+)/";
		$lines = explode("\n", $text);
		$replaces = $matches = array();
		foreach($lines as $line){
			if(preg_match($pattern, $line, $results)){
				$matches[] = $results[1];
			} else {
				if(count($matches)){
					$replaces[] = '<blockquote>'. implode("\n", $matches) . '</blockquote>';
					$matches = array();
				}
				$replaces[] = $line;
			}
		}
		$text = implode("\n", $replaces);
		return $text;
	}

	public function makeList($text)
	{
		$pattern = "/^(\s{2,})([\*\-\#])\s(.+)/";
		$nextPattern = "/^\s{4,}([^\*\-\#]+)/";
		$lines = explode("\n", $text);
		$replaces = $matches = array();
		$key = 0;
		foreach($lines as $line){
			if(preg_match($pattern, $line, $results)){
				$item = new stdClass();
				$item->type = $results[2] === '#' ? self::LIST_TYPE_ORDER : self::LIST_TYPE_UNORDER;
				$item->level = intval(strlen($results[1]) / 2);
				$item->content = $results[3];
				$item->key = $key++;
				$item->children = array();
				$matches[] = $item;
			} elseif(preg_match($nextPattern, $line, $results)) { // multiple list items
				$count = count($matches);
				if($count){
					$end = $matches[$count-1];
					$end->content .= $results[1];
				} else {
					$replaces[] = $line;
				}
			} else {
				if(count($matches)){
					$replaces[] = $this->makeListByArray($matches);
					$matches = array();
					$key = 0;
				}
				$replaces[] = $line;
			}
		}
		$text = implode("\n", $replaces);
		return $text;
	}

	public function makeListByArray($arr)
	{
		if(!count($arr)) return '';
		$tree = $this->buildTree($arr);
		return $this->traverseTree($tree);
	}

	/**
	 * build tree structure by array
	 */
	public function buildTree($arr)
	{
		$count = count($arr);
		if(!$count) return array();

		$first = $arr[0];
		$entryLevel = $first->level;
		$tree = new stdClass();
		$tree->type = $first->type;
		$tree->content = null;
		$tree->parent = null;
		$tree->key = 'root';
		$tree->level = $entryLevel - 1;
		$tree->children = array();
		$lastNode = $tree;
		// store the relations
		$parentList = array();
		foreach($arr as $key => $val){
			$level = $val->level;
			$lastLevel = $lastNode->level;
			if($level > $lastLevel){
				$lastNode->children[] = $val;
				$parentList[$key] = $lastNode;
				$lastNode = $val;
			} elseif($level < $lastLevel) { 
				// look for the node has same level 
				while($level < $lastNode->level){
					$lastNode = $parentList[$lastNode->key];
				}
				$parent = $parentList[$lastNode->key];
				$parent->children[] = $val;
				$parentList[$key] = $parent;
				$lastNode = $val;
			} else { // $level == $lastLevel
				$parent = $parentList[$lastNode->key];
				$parent->children[] = $val;
				$parentList[$key] = $parent;
				$lastNode = $val;
			}
		}
		return $tree;
	}

	/**
	 * traverse the tree
	 */
	public function traverseTree($tree)
	{
		if(!count($tree->children)) return '';
		$isOrder = $tree->children[0]->type == self::LIST_TYPE_ORDER;

		$ret = array();
		$ret[] = str_repeat(self::FORMAT_TAB, $tree->level);
		$ret[] = ($isOrder ? '<ol' : '<ul') . ' class="list list-'.$tree->level.'"' . ">\n";
		foreach($tree->children as $val){
			$hasChildren = count($val->children) > 0;
			$ret[] = str_repeat(self::FORMAT_TAB, $val->level) . '<li>'. $val->content;
			if($hasChildren){
				$ret[] = "\n";
				$ret[] = $this->traverseTree($val) . "";
				$ret[] = str_repeat(self::FORMAT_TAB, $val->level);
			}
			$ret[] = '</li>' . "\n";
		}
		$ret[] = str_repeat(self::FORMAT_TAB, $tree->level);
		$ret[] = ($isOrder ? '</ol>' : '</ul>');
		if($tree->level > 0){
			$ret[] = "\n";
		}
		return implode('', $ret);
	}

	public function makeDefinition($text)
	{
		$pattern = "/^([^\s\t=<].+)\s*::\s*([^\s\t=<].+)/";
		$lines = explode("\n", $text);
		$replaces = $matches = array();
		$key = 0;
		foreach($lines as $line){
			if(preg_match($pattern, $line, $results)){
				$item = array('dt'=>$results[1], 'dd'=>$results[2]);
				$matches[] = $item;
			} else {
				if(count($matches)){
					$replaces[] = $this->makeDefinitionByArray($matches);
					$matches = array();
					$key = 0;
				}
				$replaces[] = $line;
			}
		}
		$text = implode("\n", $replaces);
		return $text;
	}

	public function makeDefinitionByArray($arr)
	{
		if(!count($arr)) return '';
		$ret = array('<dl>' . "\n");
		foreach($arr as $key => $val){
			$ret[] = self::FORMAT_TAB . '<dt>' . $val['dt'] . '</dt>' . "\n";
			$ret[] = self::FORMAT_TAB . '<dd>' . $val['dd'] . '</dd>' . "\n";
		}
		$ret[] = '</dl>';
		return implode('', $ret);
	}

	public function makeTable($text)
	{
		$pattern = "/^(\s*)(\|.+\|)\s*$/";
		$separatePattern = "/^\|\-+[\-\+]+\-+\|$/";
		$lines = explode("\n", $text);
		$replaces = $matches = array();
		$columnNum = $isCenter = null;
		foreach($lines as $line){
			if(preg_match($pattern, $line, $results)){
				$text = $results[2];
				if(preg_match($separatePattern, $text)){
					foreach($matches as &$m){
						$m['type'] = self::TABLE_TYPE_HEAD;
					}
				} else {
					$rowArr = explode('|', $text);
					// filter empty strings
					$rowArr = array_filter($rowArr);
					// strip whitespace 
					$rowArr = array_map('trim', $rowArr);
					// reset $rowArr keys
					$rowArr = array_values($rowArr);
					$row = array('list' => $rowArr, 'type' => self::TABLE_TYPE_DATA);
					$matches[] = $row;
					if(!isset($columnNum)){
						$coloumNum = count($rowArr);
					}
					if(!isset($isCenter)){
						$isCenter= !empty($results[1]);
					}
				}
			} else {
				if(count($matches)){
					$replaces[] = $this->makeTableByArray($matches, $coloumNum, $isCenter);
					$matches = array();
					$columnNum = $isCenter = null;
				}
				$replaces[] = $line;
			}
		}
		$text = implode("\n", $replaces);
		return $text;
	}

	/**
	 * make table by rows array
	 * the column number of first row is the column number of table. 
	 * @param {array} $arr rows
	 * @param {int} $columnNum 
	 * @param {bool} $isCenter 
	 */
	public function makeTableByArray($arr, $columnNum=1, $isCenter=false)
	{
		if(!count($arr)) return '';
		$ret = array('<table'.($isCenter ? ' class="center"':'').'>');
		foreach($arr as $key => $val){
			$ret[] = self::FORMAT_TAB . '<tr>';
			$list = $val['list'];
			$tpl = ($val['type'] == VimwikiParser::TABLE_TYPE_HEAD) ? '<th>%s</th>' : '<td>%s</td>';
			for($i = 0; $i < $columnNum; $i++){
				$cell = isset($list[$i]) ? $list[$i] : '&nbsp;';
				$ret[] = str_repeat(self::FORMAT_TAB, 2) . sprintf($tpl, $cell);
			}
			$ret[] = self::FORMAT_TAB . '</tr>';
		}
		$ret[] = '</table>';
		return implode("\n", $ret);
	}

}
