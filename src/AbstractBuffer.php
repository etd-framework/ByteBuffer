<?php

namespace EtdSolutions\ByteBuffer;

abstract class AbstractBuffer implements ReadableBuffer, WriteableBuffer {
	abstract public function __construct($length);
	abstract public function __toString();
	abstract public function length();
}
