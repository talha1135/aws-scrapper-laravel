<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class FileUploadController extends Controller
{
    public function index()
    {
        return view('file-upload');
    }

    public function upload(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'file' => 'required|mimes:xlsx|max:10240', // 10MB max file size
        ]);

        // Save the uploaded file
        $file = $request->file('file');
        $uniqueFileName = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads', $uniqueFileName);

        // Ensure the processed_files directory exists
        $processedFilesDir = storage_path('app/processed_files');
        if (!file_exists($processedFilesDir)) {
            mkdir($processedFilesDir, 0777, true); // Create directory if not exists
        }

        try {
            // Load the Excel file
            $spreadsheet = new Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();

            // Set column headers for the new file
            $worksheet->setCellValue('A1', 'ASIN');
            $worksheet->setCellValue('B1', 'EU Responsible Person Name');
            $worksheet->setCellValue('C1', 'EU Responsible Person Address');
            $worksheet->setCellValue('D1', 'EU Responsible Person Email');
            $worksheet->setCellValue('E1', 'Manufacturer Name');
            $worksheet->setCellValue('F1', 'Manufacturer Address');
            $worksheet->setCellValue('G1', 'Manufacturer Contact');

            // Fetch ASINs from the uploaded file (Assuming ASINs are in column B)
            $asins = $this->extractAsinsFromExcel(storage_path('app/' . $path));

            // Process each ASIN and fetch additional details
            $rowNum = 2; // Start at row 2
            $client = new Client();
            foreach ($asins as $asin) {
                // Skip if ASIN is empty
                if (!$asin) continue;

                // Fetch manufacturer and EU responsible person details
                $data = $this->fetchManufacturerData($asin, $client);

                // Populate data into Excel
                $worksheet->setCellValue('A' . $rowNum, $asin);
                $worksheet->setCellValue('B' . $rowNum, $data['eu_name']);
                $worksheet->setCellValue('C' . $rowNum, $data['eu_address']);
                $worksheet->setCellValue('D' . $rowNum, $data['eu_email']);
                $worksheet->setCellValue('E' . $rowNum, $data['manufacturer_name']);
                $worksheet->setCellValue('F' . $rowNum, $data['manufacturer_address']);
                $worksheet->setCellValue('G' . $rowNum, $data['manufacturer_contact']);
                $rowNum++;
            }

            // Save the processed file
            $processedFileName = uniqid() . '_processed.xlsx';
            $processedFilePath = $processedFilesDir . '/' . $processedFileName;
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($processedFilePath);

            session()->flash('fileName', $processedFileName);

            return back()->with('success', 'File processed successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    public function download($filename)
    {
        $filePath = storage_path('app/processed_files/' . $filename);

        if (file_exists($filePath)) {
            return response()->download($filePath)->deleteFileAfterSend(true);
        }

        return back()->with('error', 'File not found');
    }

    // Extract ASINs from the uploaded Excel file
    private function extractAsinsFromExcel($filePath)
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $asins = [];

        $highestRow = $worksheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) { // Start from row 2
            $asin = $worksheet->getCell('B' . $row)->getValue(); // Assuming ASINs are in column B
            if (!empty($asin)) {
                $asins[] = $asin;
            }
        }

        return $asins;
    }

    // Fetch manufacturer and EU responsible person details for an ASIN
    private function fetchManufacturerData($asin, $client)
    {
        $headers = [
            'accept' => 'text/html, application/json',
            'content-type' => 'application/json',
            'x-amz-acp-params' => 'tok=FBsk2BFo33RUH3sujiaU_dkdakUcEBnthvUxK3jaTj4;ts=1734623286395;rid=YPAQAPMK7HS057YPN4AD',
            'cookie' => 'session-id=261-5758951-0539711; session-token=XYZ',
            'Referer' => 'https://www.amazon.de/dp/B0BJ1Q3HWZ',
        ];

        try {
            $response = $client->post('https://www.amazon.de/acp/buffet-mobile-card/buffet-mobile-card-3e67eb5a-92a5-4eae-9a4d-c1d3082690fb-1734571386882/getRspManufacturerContent', [
                'json' => ['asin' => $asin],
                'headers' => $headers
            ]);

            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Extract EU Responsible Person details
            $euResponsiblePerson = [
                'name' => $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-rsp-content .a-box .a-box-inner .a-size-base.a-text-bold')->first()->getNode(0)),
                'address' => implode(', ', [
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-rsp-content .a-box .a-box-inner .a-list-item')->eq(1)->getNode(0)),
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-rsp-content .a-box .a-box-inner .a-list-item')->eq(2)->getNode(0)),
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-rsp-content .a-box .a-box-inner .a-list-item')->eq(3)->getNode(0))
                ]),
                'email' => $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-rsp-content .a-box .a-box-inner .a-spacing-top-small .a-list-item')->first()->getNode(0))
            ];

            // Extract Manufacturer details
            $manufacturerInfo = [
                'name' => $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-manufacturer-content .a-box .a-box-inner .a-size-base.a-text-bold')->first()->getNode(0)),
                'address' => implode(', ', [
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-manufacturer-content .a-box .a-box-inner .a-list-item')->eq(0)->getNode(0)),
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-manufacturer-content .a-box .a-box-inner .a-list-item')->eq(1)->getNode(0)),
                    $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-manufacturer-content .a-box .a-box-inner .a-list-item')->eq(2)->getNode(0))
                ]),
                'contact' => $this->safeGetText($crawler->filter('#buffet-sidesheet-mobile-manufacturer-content .a-box .a-box-inner .a-spacing-top-small .a-list-item')->first()->getNode(0))
            ];

            // Return the manufacturer and EU responsible person data
            return [
                'eu_name' => $euResponsiblePerson['name'] ?: 'N/A',
                'eu_address' => $euResponsiblePerson['address'] ?: 'N/A',
                'eu_email' => $euResponsiblePerson['email'] ?: 'N/A',
                'manufacturer_name' => $manufacturerInfo['name'] ?: 'N/A',
                'manufacturer_address' => $manufacturerInfo['address'] ?: 'N/A',
                'manufacturer_contact' => $manufacturerInfo['contact'] ?: 'N/A'
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching data for ASIN: ' . $asin . ' - ' . $e->getMessage());
            return [
                'eu_name' => 'N/A',
                'eu_address' => 'N/A',
                'eu_email' => 'N/A',
                'manufacturer_name' => 'N/A',
                'manufacturer_address' => 'N/A',
                'manufacturer_contact' => 'N/A'
            ];
        }
    }

    // Helper function to safely get text from a node
    private function safeGetText($node)
    {
        return $node ? trim($node->textContent) : '';
    }
}
