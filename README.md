# File Upload and Processing System in Laravel

## Problem

The objective is to implement a system where users can upload Excel files, process them by extracting specific data, and then provide the processed file for download. This system includes a front-end interface that displays a loading spinner during processing and only shows the download link once the file processing is complete.

## Solution

We have created a file upload and processing system in Laravel. The core functionality involves:

1. **Uploading Excel files**.
2. **Processing the files** to extract necessary data (e.g., ASINs, Manufacturer details).
3. **Saving the processed file** and providing a download link for users once processing is completed.
4. **User-friendly interface** with a loading spinner during file processing and dynamic display of the download link.

### Why Choose Laravel?

Laravel is an excellent choice for this project due to the following reasons:

- **Elegance and Simplicity**: Laravel provides an elegant syntax and built-in methods that make handling file uploads and processing seamless.
- **Blade Templating**: Laravel’s Blade templating engine is easy to use, allowing for dynamic content updates.
- **Built-in Features**: Laravel has built-in support for file handling, session management, and error handling.
- **Package Ecosystem**: Laravel has a wide variety of packages that can be easily integrated, speeding up development.

## Features

- **File Upload**: Allows users to upload Excel files with validation.
- **File Processing**: After file upload, the file is processed to extract relevant data and save it in a new file.
- **Download Link**: A link to the processed file is provided only after the file has been processed.
- **Loading Spinner**: Displays a loading spinner while the file is being processed.

## Installed Packages

1. **PhpSpreadsheet**: Used for reading and writing Excel files.
   - Install: `composer require phpoffice/phpspreadsheet`
   - This package is essential for handling the Excel files and performing the data extraction and insertion.

2. **GuzzleHTTP**: Used to send HTTP requests (optional if you fetch data from an external source).
   - Install: `composer require guzzlehttp/guzzle`
   - This is used to make HTTP requests, for example, if you need to fetch ASIN data from an external API.

3. **Bootstrap**: Used for styling the front-end interface.
   - Install: The Bootstrap CDN is used directly in the Blade template for UI styling.

## Installation Steps

### 1. Clone the repository

```bash
git clone https://github.com/your-username/file-upload-processing.git
cd file-upload-processing
```
### 2. Install dependencies
Run the following command to install Laravel dependencies:

```bash
composer install
```
### 3. Set up environment variables
Copy .env.example to .env and configure your environment variables.

```bash
cp .env.example .env
php artisan key:generate
```
### 4. Install NPM packages (optional for frontend assets)
If you want to compile frontend assets (CSS, JS), you can use:

```bash
npm install
npm run dev
```

### 5. Start the Laravel development server
```bash
php artisan serve
Now, navigate to http://localhost:8000 in your browser.
```
Code Explanation
Routes (routes/web.php)
```php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

Route::get('/', [FileUploadController::class, 'index'])->name('file.upload');
Route::post('/upload', [FileUploadController::class, 'upload'])->name('file.upload.post');
Route::get('/file/download/{fileName}', [FileUploadController::class, 'download'])->name('file.download');
```
-***Explanation**:

GET /: Displays the file upload form where users can choose an Excel file.
POST /upload: Handles the file upload and processing logic.
GET /file/download/{fileName}: Handles the file download after processing is complete.
Controller (app/Http/Controllers/FileUploadController.php)
```php
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

    // Process the file
    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    // Setting headers for the new file
    $worksheet->setCellValue('A1', 'ASIN');
    $worksheet->setCellValue('B1', 'EU Responsible Person Name');
    $worksheet->setCellValue('C1', 'EU Responsible Person Address');
    $worksheet->setCellValue('D1', 'EU Responsible Person Email');
    $worksheet->setCellValue('E1', 'Manufacturer Name');
    $worksheet->setCellValue('F1', 'Manufacturer Address');
    $worksheet->setCellValue('G1', 'Manufacturer Contact');
    
    // Fetch ASINs from the uploaded file
    $asins = $this->extractAsinsFromExcel(storage_path('app/' . $path));

    // Fetch data and populate the worksheet
    $rowNum = 2;
    foreach ($asins as $asin) {
        if (!$asin) continue;
        $data = $this->fetchManufacturerData($asin);
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
    $processedFilePath = storage_path('app/processed_files/' . $processedFileName);
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($processedFilePath);

    // Save filename to session and return success message
    session()->flash('fileName', $processedFileName);
    return back()->with('success', 'File processed successfully!');
}
```
### Explanation:

The upload method handles the validation, file saving, processing, and finally saves the processed file.
After the file is processed, we save the filename to the session and return a success message to the view.
Blade View (resources/views/file-upload.blade.php)
```html
<form id="uploadForm" action="{{ route('file.upload.post') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
        <label for="file">Choose Excel File</label>
        <input type="file" class="form-control" name="file" id="file" required>
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
    
    <div id="loading" style="display:none;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Processing...</span>
        </div>
        <p>Processing...</p>
    </div>
</form>
```
-***Explanation**:

The form allows users to upload Excel files.
The #loading div contains a spinner that is shown while the file is being processed.
The file is uploaded via a POST request to the file.upload.post route.
JavaScript for Handling the Loader (resources/views/file-upload.blade.php)
```javascript
<script>
    $(document).ready(function() {
        // Show loading indicator when form is submitted
        $('#uploadForm').on('submit', function() {
            $('#loading').show(); // Show loading spinner
        });
    });
</script>
```
-***Explanation**:

When the form is submitted, the spinner is shown, indicating that the file is being processed.
The download link is hidden until the processing is complete.
### Conclusion
This solution offers a clean, efficient way to upload, process, and provide a downloadable Excel file. Laravel's rich ecosystem and easy-to-use features, like Blade templates and built-in session management, made it an ideal choice for this task. The PhpSpreadsheet package handled the file reading/writing operations, and GuzzleHTTP (optional) could be used for fetching external data.

Feel free to modify the solution to match your project's specific requirements.