<?php
/**
 * @copyright 2013 Instaclick Inc.
 */
namespace IC\Gherkinics\Feedback;

use IC\Gherkinics\Model\Token;

/**
 * File Feedback Backet
 *
 * @author Juti Noppornpitak <jnopporn@shiroyuki.com>
 */
final class FileFeedback
{
    /**
     * @var \IC\Gherkincs\Model\Token the current token
     */
    private $token;

    /**
     * @var array
     */
    private $messageList = array();

    /**
     * Define token
     *
     * @param \IC\Gherkinics\Model\Token $token
     */
    public function setToken(Token $token = null)
    {
        $this->token = $token;
    }

    public function add($message)
    {
        $lineNumber = $this->token
            ? $this->token->getId()
            : 0;

        if ( ! isset($this->messageList[$lineNumber])) {
            $this->messageList[$lineNumber] = new TokenFeedback()
        }

        $this->messageList[$lineNumber][] = $message;
    }

    public function all()
    {
        $messageList    = array();
        $lineNumberList = array_keys($this->messageList);

        sort($lineNumberList);

        foreach ($lineNumberList as $lineNumber) {
            $messageList[$lineNumber] = $this->messageList[$lineNumber];
        }

        return $messageList;
    }
}