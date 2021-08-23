<?php


namespace App\Exceptions;

use Exception;
use Throwable;

class BinaryNodeIsChangedException extends Exception
{
    private $node;

    /**
     * BinaryNodeInUseException constructor.
     *
     * @param $node
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($node, $message = "", $code = 0, Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
        $this->setNode($node);
    }

    /**
     * @return mixed
     */
    public function getNode()
    {
        return $this->node;
    }

    /**
     * @param mixed $node
     */
    public function setNode($node): void
    {
        $this->node = $node;
    }
}
