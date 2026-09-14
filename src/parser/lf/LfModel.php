<?php

namespace madong\workflow\parser\lf;

class LfModel
{
    public function __construct(
        public readonly string $name = '',
        public readonly string $display_name = '',
        public readonly string $type = '',
        public readonly string $instance_url = '',
        public readonly string $expire_time = '',
        public readonly string $instance_no_class = '',
        public readonly ?string $pre_interceptors = null,
        public readonly ?string $post_interceptors = null,
        public readonly array  $nodes = [],
        public readonly array  $edges = [],
    ) {}

    public function getDisplayName(): string { return $this->display_name; }
    public function getInstanceUrl(): string { return $this->instance_url; }
    public function getExpireTime(): string { return $this->expire_time; }
    public function getInstanceNoClass(): string { return $this->instance_no_class; }
    public function getPreInterceptors(): ?string { return $this->pre_interceptors; }
    public function getPostInterceptors(): ?string { return $this->post_interceptors; }
}