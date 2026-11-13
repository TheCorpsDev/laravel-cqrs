<?php

namespace TheCorps\LaravelCqrs\Events;

/**
 * Fired when a command is dispatched, before it enters the pipeline.
 */
final class CommandDispatching extends MessageEvent {}
