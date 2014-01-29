PHP-Expression
==============

Simple and fast PHP expression parser, based on secure use of eval().

Using eval() is evil()
----------------------
This class tries to expose a subset of PHP functionality not by removing the
bad features, but by explicitely allowing only the good features.

IF YOU FIND ANY (POTENTIAL) SECURITY ISSUE, PLEASE REPORT!

Since PHP-Expression uses `eval()`, it can also handle PHP syntax, including
parenthesis, arithmetic, functions and more.

Things you are allowed to do
----------------------------
-	Use numbers, either floating-point or integer, including negative.
-	Use a number of different bases, such as decimal, octal, hexadecimal.
-	Use most basic math functions such as `abs()`, `min()`, `max()` and `sqrt()`.
-	Use basic arithmetic operators `+`, `-`, `*`, `/` and `%`.
-	Use parenthesis.
-	Use comparisons (`true`/`false` returns `1`/`0` decimal).
-	Use boolean operators in comparisons.
-	Use bitwise operators.

Things we've added
------------------
-	Binary numbers, using a `0b` prefix. i.e. `0b1001110`.
-	New functions/function aliasses.
-	Support to use number "types" using prefixes.

Things you should NOT be able to do
-----------------------------------
-	Access any function not explicitely permitted.
-	Access any static class method not explicitely permitted.
-	Access any class properties or functions.
-	Access any system constants.
-	Access any variables, local or global.
-	Access the Expression class itself.
-	Access arrays of any kind, using either brackets or accolades.
-	Use strings.
-	Return anything except numbers (integer or floating-point).

Available functions
-------------------
TODO

Disclaimers
-----------
Though this class has been tested and independantly reviewed by several people,
I cannot make any absolute 100% guarentee that it cannot be hacked. If you find
any potential security problem, please let us know.

Even though the Expression class itself is supposed to be secure, the return
value may still be abused if your code does not check for validity. Only numbers
can be returned, but if your code crashes on a number "666", the Expression
class cannot and will not be able to protect you. Check for valid ranges.