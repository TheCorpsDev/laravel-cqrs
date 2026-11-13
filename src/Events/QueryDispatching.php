<?php

namespace TheCorps\LaravelCqrs\Events;

/**
 * Fired when a query is dispatched, before it enters the pipeline.
 */
final class QueryDispatching extends MessageEvent {}
