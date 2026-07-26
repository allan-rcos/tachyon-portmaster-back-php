<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\IValidateSessionUseCase;
use App\Services\ILoginUseCase;
use App\Services\ISetupUseCase;
use App\Services\Interno\SetupUseCase;
use App\Services\Interno\ValidateSessionUseCase;
use App\Services\Interno\LoginUseCase;

/**
 * Authentication. {@see LoginUseCase} declares no permission — it is what runs
 * *before* anyone has a context to authorize.
 */
final class AuthProvider extends FeatureProvider
{
    public function loginUseCase(): ILoginUseCase
    {
        return new LoginUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->infra->roleRepository(),
            $this->domain->authTM(),
        );
    }

    public function validateSessionUseCase(): IValidateSessionUseCase
    {
        return new ValidateSessionUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->infra->roleRepository(),
        );
    }

    /**
     * Declares no permission, like the two above — it is the endpoint that runs
     * when no permission could yet have been granted to anyone.
     */
    public function setupUseCase(): ISetupUseCase
    {
        return new SetupUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->infra->roleRepository(),
            $this->infra->permissionRepository(),
            $this->domain->userTM(),
            $this->domain->roleTM(),
        );
    }
}
