<?php

namespace MaartenStaa\OngrStrictStandards\Sniffs\Commenting;

/**
 * Parses and verifies the variable doc comment.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

use PHP_CodeSniffer\Exceptions\RuntimeException;
use PHP_CodeSniffer\Sniffs\AbstractVariableSniff;
use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Util\Common;

if (class_exists(AbstractVariableSniff::class, true) === false) {
    throw new RuntimeException('Class PHP_CodeSniffer_Standards_AbstractVariableSniff not found');
}

/**
 * Parses and verifies the variable doc comment.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

class VariableCommentSniff extends AbstractVariableSniff
{

    /**
     * Called to process class member vars.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processMemberVar(File $phpcsFile, $stackPtr)
    {
        $tokens       = $phpcsFile->getTokens();
        $commentToken = array(
            T_COMMENT,
            T_DOC_COMMENT_CLOSE_TAG,
        );

        $commentEnd = $phpcsFile->findPrevious($commentToken, $stackPtr);
        if ($commentEnd === false) {
            $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
            return;
        }

        if ($tokens[$commentEnd]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a member variable comment', $stackPtr, 'WrongStyle');
            return;
        } elseif ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
            $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
            return;
        } else {
            // Make sure the comment we have found belongs to us.
            $commentFor = $phpcsFile->findNext(array(T_VARIABLE, T_CLASS, T_INTERFACE), ($commentEnd + 1));
            if ($commentFor !== $stackPtr) {
                $phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
                return;
            }
        }

        $commentStart = $tokens[$commentEnd]['comment_opener'];

        $foundVar = null;
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            if ($tokens[$tag]['content'] === '@var') {
                if ($foundVar !== null) {
                    $error = 'Only one @var tag is allowed in a member variable comment';
                    $phpcsFile->addError($error, $tag, 'DuplicateVar');
                } else {
                    $foundVar = $tag;
                }
            } elseif ($tokens[$tag]['content'] === '@see') {
                // Make sure the tag isn't empty.
                $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
                if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                    $error = 'Content missing for @see tag in member variable comment';
                    $phpcsFile->addError($error, $tag, 'EmptySees');
                }
            } else {
                //ONGR We allow other property tags.
//                $error = '%s tag is not allowed in member variable comment';
//                $data  = array($tokens[$tag]['content']);
//                $phpcsFile->addWarning($error, $tag, 'TagNotAllowed', $data);
            }
        }

        // The @var tag is the only one we require.
        if ($foundVar === null) {
            $error = 'Missing @var tag in member variable comment';
            $phpcsFile->addError($error, $commentEnd, 'MissingVar');
            return;
        }

        $firstTag = $tokens[$commentStart]['comment_tags'][0];
        if ($foundVar !== null && $tokens[$firstTag]['content'] !== '@var') {
            $error = 'The @var tag must be the first tag in a member variable comment';
            $phpcsFile->addError($error, $foundVar, 'VarOrder');
        }

        // Make sure the tag isn't empty and has the correct padding.
        $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $foundVar, $commentEnd);
        if ($string === false || $tokens[$string]['line'] !== $tokens[$foundVar]['line']) {
            $error = 'Content missing for @var tag in member variable comment';
            $phpcsFile->addError($error, $foundVar, 'EmptyVar');
            return;
        }

        $varType       = $tokens[($foundVar + 2)]['content'];
//

        $suggestedType = Common::suggestType($varType);

        if ($varType === 'bool') {
            $varType = 'boolean';
        } elseif ($varType === 'int') {
            $varType = 'integer';
        }
        if ($varType !== $suggestedType && strpos($varType, $suggestedType . ' ') === false) {
            $error = 'Expected "%s" but found "%s" for @var tag in member variable comment';
            $data  = array(
                $suggestedType,
                $varType,
            );
            $phpcsFile->addError($error, ($foundVar + 2), 'IncorrectVarType', $data);
        }

        //Ongr

        $comment = trim(preg_replace('/^array\(\s*([^\s^=^>]*)(\s*=>\s*(.*))?\s*\)/i', '', $varType, 1, $count));
        if (!$count) {
            $space = strpos($comment, ' ');
            if ($space === false) {
                return;
            }
            $comment = substr($comment, $space + 1);
        }
        if ($comment === '') {
            return;
        }
        if (substr($comment, 0, 1) == '$') {
            $this->currentFile->addError(
                'Class field docs should not contain field name',
                $foundVar
            );
            return;
        }
        if (!in_array(substr($comment, -1, 1), ['.', '?', '!'])) {
            $this->currentFile->addError(
                'Variable comments must end in full-stops, exclamation marks, or question marks',
                $foundVar,
                'VariableComment'
            );
        }
        $firstLetter = substr($comment, 0, 1);
        if (strtoupper($firstLetter) !== $firstLetter) {
            $this->currentFile->addError(
                'Variable comments must must start with a capital letter',
                $foundVar,
                'VariableComment'
            );
        }
    }

    /**
     * Called to process a normal variable.
     *
     * Not required for this sniff.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                  $stackPtr  The position where the double quoted
     *                                        string was found.
     *
     * @return void
     */
    protected function processVariable(File $phpcsFile, $stackPtr)
    {
    }

    /**
     * Called to process variables found in double quoted strings.
     *
     * Not required for this sniff.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
     * @param int                  $stackPtr  The position where the double quoted
     *                                        string was found.
     *
     * @return void
     */
    protected function processVariableInString(File $phpcsFile, $stackPtr)
    {
    }
}