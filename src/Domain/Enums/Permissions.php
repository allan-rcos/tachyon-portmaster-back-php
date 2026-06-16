<?php

namespace Domain\Enums;

enum Permissions: int
{
    case PRODUCT_READ = 1;
    case PRODUCT_CREATE = 2;
    case PRODUCT_UPDATE = 3;
    case PRODUCT_DELETE = 4;

    case CONTAINER_READ = 5;
    case CONTAINER_CREATE = 6;
    case CONTAINER_UPDATE = 7;
    case CONTAINER_DELETE = 8;
    case CONTAINER_SEAL = 11;
    case CONTAINER_DISPATCH = 12;
    case CONTAINER_SUMMARY = 13;

    case MANIFEST_LOAD = 9;
    case MANIFEST_UNLOAD = 10;
}
