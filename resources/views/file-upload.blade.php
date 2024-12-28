<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Upload</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #loading {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h2>Upload Excel File</h2>

    <!-- Success message -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Error message -->
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- File upload message -->
    @if(session('fileName'))
        <div class="alert alert-info" id="download-link">
            <a href="{{ route('file.download', session('fileName')) }}" class="btn btn-success">Download Processed File</a>
        </div>
    @else
        <div class="alert alert-info" id="download-link" style="display:none;">
            <a href="" class="btn btn-success">Download Processed File</a>
        </div>
    @endif

    <form id="uploadForm" action="{{ route('file.upload.post') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label for="file">Choose Excel File</label>
            <input type="file" class="form-control @error('file') is-invalid @enderror" name="file" id="file" required>
            
            @error('file')
                <div class="invalid-feedback">
                    {{ $message }}
                </div>
            @enderror
        </div>
        <button type="submit" class="btn btn-primary">Upload</button>

        <!-- Loading Indicator -->
        <div id="loading" class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="sr-only">Processing...</span>
            </div>
            <p>Processing...</p>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
    $(document).ready(function() {
        // Show loading indicator when form is submitted
        $('#uploadForm').on('submit', function() {
            $('#loading').show(); // Show loading indicator
            $('#download-link').hide(); // Hide download link until processing is done
        });

        // Display the download link once the file is processed
        @if(session('fileName'))
            $('#loading').hide(); // Hide loader
            $('#download-link').show(); // Show download link
        @endif
    });
</script>
</body>
</html>
