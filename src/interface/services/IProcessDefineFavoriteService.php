<?php

namespace madong\workflow\interface\services;

use madong\workflow\interface\model\IProcessDefineFavorite;

interface IProcessDefineFavoriteService
{
    public function addFavorite(string $defineId, string $userId): IProcessDefineFavorite;
    public function removeFavorite(string $defineId, string $userId): void;
    public function getFavorites(string $userId): array;
}
