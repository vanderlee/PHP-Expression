<?php

class test_hack_xss extends UnitTestCase {
	function testCssLocator() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('\';alert(String.fromCharCode(88,83,83))//\';alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//";alert(String.fromCharCode(88,83,83))//--></SCRIPT>">\'><SCRIPT>alert(String.fromCharCode(88,83,83))</SCRIPT>');
	}

	function testCssLocator2() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('\'\';!--"<XSS>=&{()}');
	}

	function testNoFilterEvasion() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<SCRIPT SRC=http://ha.ckers.org/xss.js></SCRIPT>');
	}

	function testImageJs() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="javascript:alert(\'XSS\');">');
	}

	function testImageJsNoQuotesAndNoSemicolon() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=javascript:alert(\'XSS\')>');
	}

	function testImageJsGraveAccent() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=`javascript:alert("RSnake says, \'XSS\'")`>');
	}

	function testMalformedA() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<a onmouseover="alert(document.cookie)">xxs link</a>');
	}

	function testMalformedANoQuotes() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<a onmouseover=alert(document.cookie)>xxs link</a>');
	}

	function testMalformedImg() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG """><SCRIPT>alert("XSS")</SCRIPT>">');
	}

	function testImgJsFromCharCode() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=javascript:alert(String.fromCharCode(88,83,83))>');
	}

	function testDefaultSrc() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=# onmouseover="alert(\'xxs\')">');
	}

	function testDefaultSrcEmpty() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC= onmouseover="alert(\'xxs\')">');
	}

	function testDefaultSrcNone() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG onmouseover="alert(\'xxs\')">');
	}

	function testDecimalHtmlChars() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>');
	}

	function testDecimalHtmlCharsNoSemicolon() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>');
	}

	function testHexHtmlCharsNoSemicolon() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>');
	}

	function testEmbeddedTab() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="jav	ascript:alert(\'XSS\');">');
	}

	function testEmbeddedEncodedTab() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="jav&#x09;ascript:alert(\'XSS\');">');
	}

	function testEmbeddedNewline() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="jav&#x0A;ascript:alert(\'XSS\');">');
	}

	function testEmbeddedCr() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="jav&#x0D;ascript:alert(\'XSS\');">');
	}
	
	function testEmbeddedNull() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC="jav&#x00;ascript:alert(\'XSS\');">');
	}

	function testSpaceAndMetaChars() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<IMG SRC=" &#14;  javascript:alert(\'XSS\');">');
	}
	
	function testNonAlphaNonDigit() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<SCRIPT/XSS SRC="http://ha.ckers.org/xss.js"></SCRIPT>');
	}

	function testNonAlphaNonDigit2() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<BODY onload!#$%&()*~+-_.,:;?@[/|\]^`=alert("XSS")>');
	}

	function testNonAlphaNonDigit3() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<SCRIPT/SRC="http://ha.ckers.org/xss.js"></SCRIPT>');
	}

	function testExtraneousOpenBrackets() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<<SCRIPT>alert("XSS");//<</SCRIPT>');
	}

	function testNoClosingScriptTags() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<SCRIPT SRC=http://ha.ckers.org/xss.js?< B >');
	}

	function testProtocolResolution() {
		$E = new Expression;
		$this->expectException('ExpressionException');
		$E->evaluate('<SCRIPT SRC=//ha.ckers.org/.j>');
	}
}