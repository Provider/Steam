<?php
declare(strict_types=1);

namespace ScriptFUSION\Porter\Provider\Steam\Resource\Curator\CuratorList;

final class CuratorList
{
    /**
     * @var string
     */
    private $listId = '';

    /**
     * @var string[]
     */
    private $appIds = [];

    /**
     * @var string
     */
    private $title = '';

    /**
     * @var string
     */
    private $description = '';

    public function getListId(): string
    {
        return $this->listId;
    }

    public function setListId(string $listId): void
    {
        $this->listId = $listId;
    }

    public function getAppIds(): array
    {
        return $this->appIds;
    }

    public function setAppIds(array $appIds): void
    {
        $this->appIds = $appIds;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }
}
