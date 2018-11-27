<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator;

final class CuratorReview
{
    private $appId;

    private $body;

    private $recommendationState;

    /**
     * @var string
     */
    private $url = '';

    public function __construct(int $appId, string $body, RecommendationState $recommendationState)
    {
        $this->appId = $appId;
        $this->body = $body;
        $this->recommendationState = $recommendationState;
    }

    public function getAppId(): int
    {
        return $this->appId;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getRecommendationState(): RecommendationState
    {
        return $this->recommendationState;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
