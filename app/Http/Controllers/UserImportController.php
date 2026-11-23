<?php

namespace App\Http\Controllers;

use App\Actions\Users\CommitUserImport;
use App\Actions\Users\PreviewUserImport;
use App\Enums\ExportFormat;
use App\Exports\UserImportTemplateExport;
use App\Http\Requests\DownloadUserImportTemplateRequest;
use App\Http\Requests\PreviewUserImportRequest;
use App\Http\Resources\UserImportPreviewResource;
use App\Http\Resources\UserImportResultResource;
use App\Models\UserImportBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class UserImportController extends Controller
{
    /**
     * Download the empty spreadsheet admins fill before previewing an import.
     */
    public function template(
        DownloadUserImportTemplateRequest $request,
        Excel $excel,
    ): BinaryFileResponse {
        $format = ExportFormat::from($request->validated('format') ?? ExportFormat::Xlsx->value);
        $extension = $format->value;

        return $excel->download(
            new UserImportTemplateExport,
            'grr-user-import-template.'.$extension,
            $format === ExportFormat::Xlsx ? Excel::XLSX : Excel::CSV,
            $format === ExportFormat::Csv ? ['Content-Type' => 'text/csv'] : [],
        );
    }

    /**
     * Parse a spreadsheet and return the decisions for review. No account is
     * created or changed until the returned preview id is confirmed.
     */
    public function preview(
        PreviewUserImportRequest $request,
        PreviewUserImport $previewUserImport,
    ): UserImportPreviewResource {
        return new UserImportPreviewResource(
            $previewUserImport->handle($request->user(), $request->file('file')),
        );
    }

    /**
     * Apply a reviewed preview. Temporary passwords appear in this response
     * only, once per created or reset account.
     */
    public function confirm(
        Request $request,
        UserImportBatch $userImportBatch,
        CommitUserImport $commitUserImport,
    ): JsonResponse {
        return (new UserImportResultResource(
            $commitUserImport->handle($request->user(), $userImportBatch),
        ))->response()->setStatusCode(Response::HTTP_OK);
    }
}
