<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ส่งหลักฐานการโอนเงินค่าบำรุงสี - วิทยาลัยการอาชีพปราสาท</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 900px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .content-section {
            padding: 2rem;
        }
        
        .bank-info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid;
        }
        
        .color-badge {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
            border: 3px solid #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,0.3);
        }
        
        .form-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .upload-area {
            border: 2px dashed #667eea;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: #f8f9ff;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #764ba2;
            background: #f0f2ff;
        }
        
        .upload-area.dragover {
            border-color: #28a745;
            background: #f0fff4;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        
        .slip-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }
        
        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: 1;
        }
        
        .step:last-child::after {
            display: none;
        }
        
        .step.active .step-circle {
            background: #667eea;
            color: white;
        }
        
        .step.completed .step-circle {
            background: #28a745;
            color: white;
        }
        
        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <div class="mb-3">
                    <i class="fas fa-money-bill-transfer fa-3x"></i>
                </div>
                <h1><i class="fas fa-upload me-2"></i>ส่งหลักฐานการโอนเงิน</h1>
                <p class="mb-0">ค่าบำรุงกิจกรรมกีฬาสี</p>
                <p class="mt-2 mb-0">วิทยาลัยการอาชีพปราสาท</p>
            </div>
            
            <div class="content-section">
                <!-- Search Student Section -->
                <div class="form-section mb-4">
                    <h4 class="mb-3"><i class="fas fa-search me-2"></i>ค้นหาข้อมูลของคุณ</h4>
                    <form id="searchForm" class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">รหัสนักเรียน</label>
                            <input type="text" class="form-control form-control-lg" id="studentCode" 
                                   placeholder="กรอกรหัสนักเรียน 11 หลัก" required>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-submit w-100">
                                <i class="fas fa-search me-2"></i>ค้นหา
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Student Info Section (Hidden initially) -->
                <div id="studentInfoSection" class="d-none">
                    <!-- Step Indicator -->
                    <div class="step-indicator">
                        <div class="step completed">
                            <div class="step-circle">
                                <i class="fas fa-user"></i>
                            </div>
                            <small>ตรวจสอบข้อมูล</small>
                        </div>
                        <div class="step active">
                            <div class="step-circle">
                                <i class="fas fa-money-bill"></i>
                            </div>
                            <small>โอนเงิน</small>
                        </div>
                        <div class="step">
                            <div class="step-circle">
                                <i class="fas fa-upload"></i>
                            </div>
                            <small>ส่งหลักฐาน</small>
                        </div>
                        <div class="step">
                            <div class="step-circle">
                                <i class="fas fa-check"></i>
                            </div>
                            <small>เสร็จสิ้น</small>
                        </div>
                    </div>
                    
                    <!-- Student Info Card -->
                    <div class="form-section mb-4">
                        <h5><i class="fas fa-user me-2 text-primary"></i>ข้อมูลของคุณ</h5>
                        <div id="studentInfoDisplay"></div>
                    </div>
                    
                    <!-- Bank Info Card -->
                    <div id="bankInfoCard">
                        <!-- จะแสดงข้อมูลธนาคารที่นี่ -->
                    </div>
                    
                    <!-- Upload Form -->
                    <div class="form-section">
                        <h5><i class="fas fa-upload me-2 text-success"></i>อัพโหลดหลักฐานการโอนเงิน</h5>
                        <form id="uploadForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">จำนวนเงินที่โอน <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="amount" 
                                                   step="0.01" min="0" required readonly>
                                            <span class="input-group-text">บาท</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">วันที่โอนเงิน <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="transferDate" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">เวลาที่โอนเงิน</label>
                                        <input type="time" class="form-control" id="transferTime">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">ธนาคารต้นทาง</label>
                                        <select class="form-select" id="bankFrom">
                                            <option value="">เลือกธนาคาร</option>
                                            <option value="ธนาคารกรุงไทย">ธนาคารกรุงไทย</option>
                                            <option value="ธนาคารกสิกรไทย">ธนาคารกสิกรไทย</option>
                                            <option value="ธนาคารไทยพาณิชย์">ธนาคารไทยพาณิชย์</option>
                                            <option value="ธนาคารกรุงเทพ">ธนาคารกรุงเทพ</option>
                                            <option value="ธนาคารกรุงศรีอยุธยา">ธนาคารกรุงศรีอยุธยา</option>
                                            <option value="ธนาคารทหารไทยธนชาต">ธนาคารทหารไทยธนชาต</option>
                                            <option value="ธนาคารออมสิน">ธนาคารออมสิน</option>
                                            <option value="ธนาคารอาคารสงเคราะห์">ธนาคารอาคารสงเคราะห์</option>
                                            <option value="ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร">ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร</option>
                                            <option value="ธนาคารอิสลามแห่งประเทศไทย">ธนาคารอิสลามแห่งประเทศไทย</option>
                                            <option value="อื่นๆ">อื่นๆ</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">หมายเลขอ้างอิง</label>
                                        <input type="text" class="form-control" id="refNumber" 
                                               placeholder="หมายเลขอ้างอิงจากสลิป">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">อัพโหลดรูปสลิป <span class="text-danger">*</span></label>
                                        <div class="upload-area" onclick="document.getElementById('slipImage').click()">
                                            <div id="uploadPrompt">
                                                <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                                <p class="mb-2"><strong>คลิกเพื่อเลือกไฟล์</strong></p>
                                                <p class="text-muted mb-0">หรือลากไฟล์มาวางที่นี่</p>
                                                <small class="text-muted">รองรับไฟล์: JPG, JPEG, PNG (ขนาดไม่เกิน 5MB)</small>
                                            </div>
                                            <div id="imagePreview" class="d-none">
                                                <img id="previewImg" class="slip-preview" alt="ตัวอย่างสลิป">
                                                <p class="mt-2 mb-0">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeImage()">
                                                        <i class="fas fa-trash"></i> ลบภาพ
                                                    </button>
                                                </p>
                                            </div>
                                        </div>
                                        <input type="file" id="slipImage" name="slip_image" 
                                               accept="image/jpeg,image/jpg,image/png" 
                                               style="display: none;" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">หมายเหตุเพิ่มเติม</label>
                                        <textarea class="form-control" id="notes" rows="3" 
                                                  placeholder="ข้อมูลเพิ่มเติม (ถ้ามี)"></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-submit btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>ส่งหลักฐานการโอนเงิน
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Loading Section -->
                <div id="loadingSection" class="text-center d-none">
                    <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">กำลังโหลด...</span>
                    </div>
                    <p class="mt-3">กำลังดำเนินการ...</p>
                </div>
                
                <!-- Success Section -->
                <div id="successSection" class="text-center d-none">
                    <div class="mb-4">
                        <i class="fas fa-check-circle fa-5x text-success"></i>
                    </div>
                    <h3 class="text-success">ส่งหลักฐานการโอนเงินเรียบร้อยแล้ว!</h3>
                    <p class="text-muted">
                        ระบบได้รับหลักฐานการโอนเงินของคุณแล้ว<br>
                        กรุณารอการตรวจสอบจากเจ้าหน้าที่ภายใน 1-2 วันทำการ
                    </p>
                    <div class="mt-4">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="location.reload()">
                            <i class="fas fa-plus"></i> ส่งหลักฐานใหม่
                        </button>
                        <a href="student_check.php" class="btn btn-outline-success">
                            <i class="fas fa-search"></i> ตรวจสอบสถานะ
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ข้อมูลนักเรียนและสีจำลอง (ในการใช้งานจริงจะดึงจาก API)
        const mockStudentData = {
            '12345678911': {
                student_id: 73,
                student_code: '12345678911',
                title: 'นาย',
                first_name: 'มนตรี',
                last_name: 'ศรีสุข',
                level: 'ปวช.1',
                department_name: 'เทคโนโลยีสารสนเทศ',
                group_number: 1,
                color: {
                    color_id: 1,
                    color_name: 'สีแดง',
                    color_code: '#FF0000',
                    fee_amount: 150.00,
                    bank_name: 'ธนาคารกรุงไทย',
                    bank_account_number: '1234567890',
                    bank_account_name: 'กิจกรรมนักเรียน วิทยาลัยการอาชีพปราสาท'
                }
            }
        };
        
        // ค้นหาข้อมูลนักเรียน
        document.getElementById('searchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const studentCode = document.getElementById('studentCode').value.trim();
            
            if (!studentCode) {
                alert('กรุณากรอกรหัสนักเรียน');
                return;
            }
            
            // แสดง loading
            document.getElementById('loadingSection').classList.remove('d-none');
            
            // จำลองการค้นหา
            setTimeout(() => {
                document.getElementById('loadingSection').classList.add('d-none');
                
                if (mockStudentData[studentCode]) {
                    showStudentInfo(mockStudentData[studentCode]);
                } else {
                    alert('ไม่พบข้อมูลนักเรียน หรือ นักเรียนยังไม่ได้รับการจัดสี');
                }
            }, 1000);
        });
        
        // แสดงข้อมูลนักเรียน
        function showStudentInfo(student) {
            const studentInfoDisplay = document.getElementById('studentInfoDisplay');
            const bankInfoCard = document.getElementById('bankInfoCard');
            const amount = document.getElementById('amount');
            
            // แสดงข้อมูลนักเรียน
            studentInfoDisplay.innerHTML = `
                <div class="row">
                    <div class="col-md-8">
                        <h6><strong>${student.title}${student.first_name} ${student.last_name}</strong></h6>
                        <p class="mb-1"><strong>รหัสนักเรียน:</strong> ${student.student_code}</p>
                        <p class="mb-1"><strong>ชั้นเรียน:</strong> ${student.level} ${student.department_name} ${student.group_number}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <span class="color-badge" style="background-color: ${student.color.color_code}"></span>
                            <strong>${student.color.color_name}</strong>
                        </div>
                    </div>
                </div>
            `;
            
            // แสดงข้อมูลธนาคาร
            bankInfoCard.innerHTML = `
                <div class="bank-info-card" style="border-left-color: ${student.color.color_code}">
                    <h5><i class="fas fa-university me-2"></i>ข้อมูลการโอนเงิน ${student.color.color_name}</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>ธนาคาร:</strong> ${student.color.bank_name}</p>
                            <p class="mb-2"><strong>เลขที่บัญชี:</strong> ${student.color.bank_account_number}</p>
                            <p class="mb-2"><strong>ชื่อบัญชี:</strong> ${student.color.bank_account_name}</p>
                        </div>
                        <div class="col-md-6">
                            <div class="text-end">
                                <p class="mb-1"><strong>จำนวนเงินที่ต้องโอน</strong></p>
                                <h3 class="text-success mb-0">${student.color.fee_amount.toLocaleString('th-TH', {minimumFractionDigits: 2})} บาท</h3>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>หมายเหตุ:</strong> กรุณาโอนเงินให้ตรงตามจำนวนที่กำหนด และเก็บหลักฐานการโอนเงินไว้อัพโหลดในขั้นตอนถัดไป
                    </div>
                </div>
            `;
            
            // ตั้งค่าจำนวนเงิน
            amount.value = student.color.fee_amount.toFixed(2);
            
            // ตั้งค่าวันที่เป็นวันปัจจุบัน
            document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
            
            // แสดงส่วนข้อมูลนักเรียน
            document.getElementById('studentInfoSection').classList.remove('d-none');
            
            // เลื่อนไปยังส่วนข้อมูลนักเรียน
            document.getElementById('studentInfoSection').scrollIntoView({ behavior: 'smooth' });
        }
        
        // จัดการการอัพโหลดไฟล์
        const slipImage = document.getElementById('slipImage');
        const uploadArea = document.querySelector('.upload-area');
        const uploadPrompt = document.getElementById('uploadPrompt');
        const imagePreview = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        slipImage.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                slipImage.files = files;
                handleFileSelect();
            }
        });
        
        function handleFileSelect() {
            const file = slipImage.files[0];
            
            if (!file) return;
            
            // ตรวจสอบประเภทไฟล์
            if (!file.type.match('image.*')) {
                alert('กรุณาเลือกไฟล์ภาพเท่านั้น');
                slipImage.value = '';
                return;
            }
            
            // ตรวจสอบขนาดไฟล์ (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('ขนาดไฟล์ต้องไม่เกิน 5MB');
                slipImage.value = '';
                return;
            }
            
            // แสดงตัวอย่างภาพ
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                uploadPrompt.classList.add('d-none');
                imagePreview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
        }
        
        function removeImage() {
            slipImage.value = '';
            uploadPrompt.classList.remove('d-none');
            imagePreview.classList.add('d-none');
        }
        
        // ส่งข้อมูล
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // ตรวจสอบข้อมูล
            if (!slipImage.files[0]) {
                alert('กรุณาเลือกรูปสลิป');
                return;
            }
            
            if (!document.getElementById('transferDate').value) {
                alert('กรุณาเลือกวันที่โอนเงิน');
                return;
            }
            
            // แสดง loading
            document.getElementById('studentInfoSection').classList.add('d-none');
            document.getElementById('loadingSection').classList.remove('d-none');
            
            // จำลองการส่งข้อมูล
            setTimeout(() => {
                document.getElementById('loadingSection').classList.add('d-none');
                document.getElementById('successSection').classList.remove('d-none');
                
                // อัพเดท step indicator
                document.querySelector('.step:last-child').classList.add('completed');
            }, 2000);
        });
        
        // ตั้งค่าวันที่สูงสุดเป็นวันปัจจุบัน
        document.getElementById('transferDate').max = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>