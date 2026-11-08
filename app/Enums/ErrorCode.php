<?php

namespace App\Enums;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stable, machine-readable identifiers for expected API errors.
 *
 * Flutter must branch on `code`, never on `message`. The values are part of the
 * public contract: never rename one without a client release and, if a code is
 * removed, a documented deprecation.
 */
enum ErrorCode: string
{
    case Unauthenticated = 'unauthenticated';
    case InvalidCredentials = 'invalid_credentials';
    case AccountPending = 'account_pending';
    case AccountInactive = 'account_inactive';
    case PasswordChangeRequired = 'password_change_required';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case ValidationFailed = 'validation_failed';
    case TooManyRequests = 'too_many_requests';
    case HttpError = 'http_error';

    public function httpStatus(): int
    {
        return match ($this) {
            self::Unauthenticated, self::InvalidCredentials => Response::HTTP_UNAUTHORIZED,
            self::AccountPending,
            self::AccountInactive,
            self::PasswordChangeRequired,
            self::Forbidden => Response::HTTP_FORBIDDEN,
            self::NotFound => Response::HTTP_NOT_FOUND,
            self::MethodNotAllowed => Response::HTTP_METHOD_NOT_ALLOWED,
            self::ValidationFailed => Response::HTTP_UNPROCESSABLE_ENTITY,
            self::TooManyRequests => Response::HTTP_TOO_MANY_REQUESTS,
            self::HttpError => Response::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * Default, non-authoritative text. Clients must not translate or match on it.
     */
    public function message(): string
    {
        return match ($this) {
            self::Unauthenticated => 'Unauthenticated.',
            self::InvalidCredentials => 'The provided credentials are incorrect.',
            self::AccountPending => 'Your account is pending approval.',
            self::AccountInactive => 'Your account has been deactivated.',
            self::PasswordChangeRequired => 'You must change your password before continuing.',
            self::Forbidden => 'You do not have permission to access this resource.',
            self::NotFound => 'Resource not found.',
            self::MethodNotAllowed => 'The HTTP method is not allowed for this route.',
            self::ValidationFailed => 'The given data was invalid.',
            self::TooManyRequests => 'Too many attempts. Please try again later.',
            self::HttpError => 'The request could not be completed.',
        };
    }

    /**
     * Map a login-blocking account status to its public error code.
     */
    public static function forAccountStatus(?UserStatus $status): self
    {
        return match ($status) {
            UserStatus::Pending => self::AccountPending,
            UserStatus::Inactive => self::AccountInactive,
            default => self::Forbidden,
        };
    }

    /**
     * Map an HTTP status to the closest generic code, used as a last resort.
     */
    public static function fromStatus(int $status): self
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => self::Unauthenticated,
            Response::HTTP_FORBIDDEN => self::Forbidden,
            Response::HTTP_NOT_FOUND => self::NotFound,
            Response::HTTP_METHOD_NOT_ALLOWED => self::MethodNotAllowed,
            Response::HTTP_UNPROCESSABLE_ENTITY => self::ValidationFailed,
            Response::HTTP_TOO_MANY_REQUESTS => self::TooManyRequests,
            default => self::HttpError,
        };
    }

    /**
     * Build the JSON error response for this code.
     *
     * @param  array<string, mixed>  $extra  Additional contract fields, e.g. `errors`.
     */
    public function response(?string $message = null, array $extra = []): JsonResponse
    {
        return response()->json([
            'message' => $message ?? $this->message(),
            'code' => $this->value,
            ...$extra,
        ], $this->httpStatus());
    }
}
