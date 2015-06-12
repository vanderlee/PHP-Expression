<?php

class HackXssTest extends PHPUnit_Framework_TestCase {
	/**
	 * @var Expression
	 */
	protected $object;

	/**
	 * Sets up the fixture, for example, opens a network connection.
	 * This method is called before a test is executed.
	 */
	protected function setUp() {		
		$this->object = new Expression();
	}
	
	public function testCssLocator() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('\';alert(String.fromCharCode(88,83,83))//\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//--></SCRIPT>">\'><SCRIPT>alert(String.fromCharCode(88,83,83))</SCRIPT>');
	}

	public function testCssLocator2() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('\'\';!--"<XSS>=&{()}');
	}

	public function testNoFilterEvasion() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<SCRIPT SRC=http://ha.ckers.org/xss.js></SCRIPT>');
	}

	public function testImageJs() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="javascript:alert(\'XSS\');">');
	}

	public function testImageJsNoQuotesAndNoSemicolon() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=javascript:alert(\'XSS\')>');
	}

	public function testImageJsGraveAccent() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=`javascript:alert("RSnake says, \'XSS\'")`>');
	}

	public function testMalformedA() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<a onmouseover="alert(document.cookie)">xxs link</a>');
	}

	public function testMalformedANoQuotes() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<a onmouseover=alert(document.cookie)>xxs link</a>');
	}

	public function testMalformedImg() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG """><SCRIPT>alert("XSS")</SCRIPT>">');
	}

	public function testImgJsFromCharCode() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=javascript:alert(String.fromCharCode(88,83,83))>');
	}

	public function testDefaultSrc() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=# onmouseover="alert(\'xxs\')">');
	}

	public function testDefaultSrcEmpty() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC= onmouseover="alert(\'xxs\')">');
	}

	public function testDefaultSrcNone() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG onmouseover="alert(\'xxs\')">');
	}

	public function testDecimalHtmlChars() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>');
	}

	public function testDecimalHtmlCharsNoSemicolon() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>');
	}

	public function testHexHtmlCharsNoSemicolon() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>');
	}

	public function testEmbeddedTab() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="jav	ascript:alert(\'XSS\');">');
	}

	public function testEmbeddedEncodedTab() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="jav&#x09;ascript:alert(\'XSS\');">');
	}

	public function testEmbeddedNewline() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="jav&#x0A;ascript:alert(\'XSS\');">');
	}

	public function testEmbeddedCr() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="jav&#x0D;ascript:alert(\'XSS\');">');
	}
	
	public function testEmbeddedNull() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC="jav&#x00;ascript:alert(\'XSS\');">');
	}

	public function testSpaceAndMetaChars() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<IMG SRC=" &#14;  javascript:alert(\'XSS\');">');
	}
	
	public function testNonAlphaNonDigit() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<SCRIPT/XSS SRC="http://ha.ckers.org/xss.js"></SCRIPT>');
	}

	public function testNonAlphaNonDigit2() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<BODY onload!#$%&()*~+-_.,:;?@[/|\]^`=alert("XSS")>');
	}

	public function testNonAlphaNonDigit3() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<SCRIPT/SRC="http://ha.ckers.org/xss.js"></SCRIPT>');
	}

	public function testExtraneousOpenBrackets() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<<SCRIPT>alert("XSS");//<</SCRIPT>');
	}

	public function testNoClosingScriptTags() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<SCRIPT SRC=http://ha.ckers.org/xss.js?< B >');
	}

	public function testProtocolResolution() {
		
		$this->setExpectedException('ExpressionException');
		$this->object->evaluate('<SCRIPT SRC=//ha.ckers.org/.j>');
	}
}