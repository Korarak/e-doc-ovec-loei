# 🖊️ LoeiTech E-Sign System – วิเคราะห์ Workflow การลงนาม

> **ระบบลงนามเอกสารอิเล็กทรอนิกส์** วิทยาลัยเทคนิคเลย
> ไฟล์นี้วิเคราะห์จากโค้ดจริงทุกไฟล์ + ฐานข้อมูล

---

## 1. ภาพรวม Workflow การลงนาม

ระบบมีขั้นตอนการลงนามทั้งหมด **3 ขั้นตอน** ตามลำดับชั้นองค์กร:

```
หนังสือเข้า → [งานสารบรรณ] → [รองผู้อำนวยการ (ตามฝ่าย)] → [ผู้อำนวยการ] → จบ
```

สถานะใน `sign_doc` ที่ควบคุม workflow นี้:

| Field | เริ่มต้น | หลังสารบรรณ | หลังรอง ผอ. | หลัง ผอ. |
|---|---|---|---|---|
| `doc_status` | - | `approve` | `approve` | `approve` |
| `sign_sarabun` | `pending` | **`approve`** | `approve` | `approve` |
| `sign_codirector` | `pending` | `pending` | **`approve`** | `approve` |
| `sign_director` | `pending` | `pending` | `pending` | **`approve`** |

---

## 2. ฐานข้อมูลที่เกี่ยวข้องกับ Workflow

### ตาราง `sign_doc` (หัวใจของ Workflow)

```sql
CREATE TABLE sign_doc (
    sign_doc_id    INT AUTO_INCREMENT PRIMARY KEY,
    doc_id         INT,               -- FK → documents.doc_id
    user_id        INT,               -- ผู้เริ่มต้น workflow
    dep_id         INT,               -- ฝ่ายที่หนังสือนี้ส่งถึง (รอง ผอ. เห็นเฉพาะ dep_id ตัวเอง)
    doc_status     VARCHAR(50),       -- 'approve' = ผ่าน Admin
    sign_sarabun   VARCHAR(50),       -- 'pending' | 'approve'
    sign_codirector VARCHAR(50),      -- 'pending' | 'approve'
    sign_director  VARCHAR(50)        -- 'pending' | 'approve'
);
```

### ตาราง `sign_detail` (เก็บ annotation บน PDF)

```sql
CREATE TABLE sign_detail (
    detail_id      INT AUTO_INCREMENT PRIMARY KEY,
    sign_doc_id    INT,               -- FK → sign_doc.sign_doc_id
    sign_file_id   INT,               -- FK → document_files.file_id
    page_num       INT,               -- หน้าที่ลงนาม
    x_pos          FLOAT,             -- พิกัด X บน Canvas (px)
    y_pos          FLOAT,             -- พิกัด Y บน Canvas (px)
    sign_txt       TEXT,              -- ข้อความที่แสดงบน PDF
    sign_pic       VARCHAR(255),      -- path ลายเซ็นรูปภาพ (NULL ถ้าไม่มี)
    sign_by        INT,               -- FK → user.user_id ผู้ลงนาม
    sign_datetime  DATETIME           -- วันเวลาที่ลงนาม
);
```

---

## 3. Workflow Flow Chart โดยละเอียด

### 3.1 ภาพรวม End-to-End

```mermaid
flowchart TD
    A([🟢 เริ่มต้น:\nหนังสือราชการเข้ามา]) --> B

    subgraph ADMIN["🔑 ขั้นที่ 0: Admin จัดการหนังสือ"]
        B[doc_add.php\nอัปโหลด PDF\nกรอกข้อมูลหนังสือ]
        B --> B1[(บันทึก\ndocuments\n+\ndocument_files)]
    end

    B1 --> C

    subgraph SARABAN["📋 ขั้นที่ 1: งานสารบรรณ\nsarabun_manage.php"]
        C{เลือกดำเนินการ}
        C -->|ลงรับ| D[sarabun_sign.php\nเลือกตราปั้มลงรับ\nคลิกตำแหน่งบน PDF]
        C -->|เกษียณ| E[sarabun_signtxt.php\nเลือกฟอร์มเกษียณ\nระบุฝ่าย + ข้อความ]

        D --> D2[sarabun_generate_1.php\nบันทึก sign_detail\nสร้าง sign_doc:\nsign_sarabun=approve]
        E --> E2[sarabun_generate_2.php\nบันทึก sign_detail+sign_pic\nอัปเดต dep_id\nsign_sarabun=approve]
    end

    D2 & E2 --> F

    subgraph CODIRECTOR["🔶 ขั้นที่ 2: รองผู้อำนวยการ\ncodirector_manage.php"]
        F[Filter:\ndoc_status=approve\nAND sign_sarabun=approve\nAND dep_id=SESSION.dep_id]
        F --> G[codirector_sign.php\nเลือกฟอร์ม:\nเสนอ ผอ. พร้อมความเห็น\nคลิกตำแหน่งบน PDF]
        G --> G2[sarabun_generate_3.php\nบันทึก sign_detail+sign_pic\nอัปเดต sign_codirector=approve]
    end

    G2 --> H

    subgraph DIRECTOR["🔴 ขั้นที่ 3: ผู้อำนวยการ\ndirector_manage.php"]
        H[Filter:\ndoc_status=approve\nAND sign_sarabun=approve\nAND sign_codirector=approve]
        H --> I[director_sign.php\nเลือก: ทราบ/แจ้ง/มอบ\n+ ลงนาม + ความเห็น\nคลิกตำแหน่งบน PDF]
        I --> I2[sarabun_generate_3.php\nบันทึก sign_detail+sign_pic\nอัปเดต sign_director=approve]
    end

    I2 --> J([✅ เสร็จสิ้น:\nเอกสารผ่านทุกขั้นตอน])

    style ADMIN fill:#e8f4fd,stroke:#2196F3
    style SARABAN fill:#e8fde8,stroke:#4CAF50
    style CODIRECTOR fill:#fff3e0,stroke:#FF9800
    style DIRECTOR fill:#fde8e8,stroke:#F44336
```

---

### 3.2 ขั้นที่ 1 – งานสารบรรณ (รายละเอียด)

```mermaid
flowchart TD
    M[sarabun_manage.php\nแสดงรายการหนังสือทั้งหมด\nกรองตาม: ประเภท / คำค้น]
    M --> N{เลือกดำเนินการ}

    N -->|"ปุ่ม [ลงรับ]"| P[sarabun_sign.php]
    N -->|"ปุ่ม [เกษียณ]"| Q[sarabun_signtxt.php]
    N -->|"ปุ่ม [ดูเอกสาร]"| R[document_preview.php\nอ่านอย่างเดียว]

    subgraph LONGRUB["sarabun_sign.php — ลงรับ"]
        P --> P1{เลือกตราปั้ม}
        P1 -->|ฟอร์ม 1| P2["ตราปั้มลงรับ\n(วิทยาลัยเทคนิคเลย\nรับที่: xxx\nวันที่: xxx\nเวลา: xxx)"]
        P1 -->|ฟอร์ม 2| P3["ตราปั้มลงเลขคำสั่ง\nประกาศ"]
        P2 & P3 --> P4[คลิกบน PDF Canvas\nเพื่อเลือกตำแหน่ง X,Y]
        P4 --> P5[กด บันทึก\n→ POST sarabun_generate_1.php]
    end

    subgraph KASIAN["sarabun_signtxt.php — เกษียณหนังสือ"]
        Q --> Q1{เลือกฟอร์ม}
        Q1 -->|ฟอร์ม 1| Q2["เกษียณ: เรียน ผอ.\nเพื่อโปรด: ทราบ/แจ้ง/มอบ\nระบุฝ่ายที่ส่งถึง (dep_id)\nลงนาม (ถ้าต้องการ)"]
        Q1 -->|ฟอร์ม 2| Q3["ตราปั้ม ทาน/พิมพ์/คำสั่ง\nระบุ: รอง ผอ, ทาน,\nหัวหน้างาน, พิมพ์"]
        Q2 & Q3 --> Q4[คลิกบน PDF Canvas\nเพื่อเลือกตำแหน่ง X,Y]
        Q4 --> Q5[กด บันทึก\n→ POST sarabun_generate_2.php]
    end

    P5 --> DB1[(sign_doc:\nsign_sarabun=approve\nsign_codirector=pending\nsign_director=pending\ndep_id=ฝ่ายที่เลือก)]
    Q5 --> DB1

    DB1 --> END1[กลับ sarabun_manage.php]
```

---

### 3.3 ขั้นที่ 2 – รองผู้อำนวยการ (รายละเอียด)

```mermaid
flowchart TD
    CO[codirector_manage.php\nแสดงหนังสือที่ filter ด้วย:\n✅ doc_status = approve\n✅ sign_sarabun = approve\n🔒 dep_id = SESSION dep_id]

    CO --> CO1{เลือกดำเนินการ}
    CO1 -->|"[เกษียณ]"| CO2[codirector_sign.php]
    CO1 -->|"[ดูเอกสาร]"| CO3[document_preview.php]

    subgraph COSIGN["codirector_sign.php"]
        CO2 --> CO4[เลือกฟอร์ม:\nตราปั้ม เสนอ ผอ.]
        CO4 --> CO5["สร้างข้อความเกษียณ:\nเรียน ผอ. วท.เลย\nเพื่อโปรด: ทราบ/พิจารณา\nเห็นควร: แจ้ง/มอบ\nความเห็น: ___\nลงนาม (optional)\nวันที่: ___"]
        CO5 --> CO6[คลิก Canvas เลือกตำแหน่ง]
        CO6 --> CO7[กด บันทึก\n→ POST sarabun_generate_3.php]
    end

    CO7 --> DB2[(sign_doc:\nUPDATE sign_codirector = approve\n\nsign_detail:\nINSERT x_pos, y_pos,\nsign_txt, sign_pic)]
    DB2 --> END2[กลับ sarabun_manage.php]
```

---

### 3.4 ขั้นที่ 3 – ผู้อำนวยการ (รายละเอียด)

```mermaid
flowchart TD
    DI[director_manage.php\nแสดงหนังสือที่ filter ด้วย:\n✅ doc_status = approve\n✅ sign_sarabun = approve\n✅ sign_codirector = approve]

    DI --> DI1{เลือกดำเนินการ}
    DI1 -->|"[เกษียณ]"| DI2[director_sign.php]
    DI1 -->|"[ดูเอกสาร]"| DI3[document_preview.php]

    subgraph DISIGN["director_sign.php"]
        DI2 --> DI4[เลือกฟอร์ม:\nผู้อำนวยการ]
        DI4 --> DI5["สร้างข้อความ:\nทราบ ✅ / แจ้ง ✅ / มอบ ✅\nลงนาม ✅ (วาดลายเซ็น)\nความเห็น: ___\nวันที่: ___"]
        DI5 --> DI6[คลิก Canvas เลือกตำแหน่ง]
        DI6 --> DI7[กด บันทึก\n→ POST sarabun_generate_3.php]
    end

    DI7 --> DB3[(sign_doc:\nUPDATE sign_director = approve\n\nsign_detail:\nINSERT x_pos, y_pos,\nsign_txt, sign_pic=ลายเซ็น)]
    DB3 --> END3[✅ กระบวนการเสร็จสมบูรณ์]
```

---

## 4. State Machine ของ sign_doc

```mermaid
stateDiagram-v2
    [*] --> ไม่มีrecord: Admin Upload PDF

    ไม่มีrecord --> S0: งานสารบรรณ\nลงรับ / เกษียณ

    state S0 {
        [*] --> pending_state
        pending_state : doc_status=approve\nsign_sarabun=approve\nsign_codirector=pending\nsign_director=pending
    }

    S0 --> S1: รอง ผอ. ลงนาม\n[sarabun_generate_3.php]

    state S1 {
        [*] --> codirector_approved
        codirector_approved : doc_status=approve\nsign_sarabun=approve\nsign_codirector=approve\nsign_director=pending
    }

    S1 --> S2: ผอ. ลงนาม\n[sarabun_generate_3.php]

    state S2 {
        [*] --> fully_approved
        fully_approved : doc_status=approve\nsign_sarabun=approve\nsign_codirector=approve\nsign_director=approve
    }

    S2 --> [*]
```

---

## 5. วิธีที่ระบบแสดง Annotation บน PDF

ทุกหน้าการลงนามใช้ **PDF.js** render ไฟล์ PDF ลงบน `<canvas>` และเมื่อ load หน้า จะดึง `sign_detail` ที่บันทึกไว้มาแสดงทับบน canvas อีกครั้ง:

```mermaid
sequenceDiagram
    participant B as Browser
    participant S as PHP Server
    participant DB as Database

    B->>S: GET sarabun_sign.php?doc_id=X&file_id=Y&page=1
    S->>DB: SELECT sign_detail WHERE doc_id=X AND page_num=1
    DB-->>S: list of {x_pos, y_pos, sign_txt, sign_pic}
    S-->>B: HTML + Canvas + Annotation DIVs

    Note over B: PDF.js render PDF ลงบน canvas<br/>Annotation DIVs ถูก overlay ทับครั้งแรก

    B->>B: User คลิกบน canvas → อัปเดต X,Y ใน form
    B->>B: User เติมข้อความ / เลือก action

    B->>S: POST sarabun_generate_1.php (ข้อความ, X, Y, page)
    S->>DB: INSERT sign_detail + UPDATE sign_doc status
    S-->>B: redirect ← sarabun_manage.php
```

---

## 6. การ Route หนังสือถึงรอง ผอ. แต่ละฝ่าย

> 🔑 **Key discovery**: ระบบใช้ `dep_id` ใน `sign_doc` เพื่อกำหนดว่าหนังสือนี้ "เสนอ" ถึงรอง ผอ. ฝ่ายใด

```mermaid
flowchart LR
    S[สารบรรณเกษียณ\nระบุฝ่าย dep_id=2]

    S --> DB1[(sign_doc\ndep_id=2\nsign_sarabun=approve)]

    DB1 -->|codirector_manage.php\nfilter: dep_id=SESSION.dep_id| CO2["รอง ผอ. ฝ่าย 2\nเห็นหนังสือนี้"]
    DB1 -->|ไม่ผ่าน filter| CO3["รอง ผอ. ฝ่าย 1, 3\nไม่เห็นหนังสือนี้"]

    CO2 --> SIGN[ลงนาม / เกษียณ\n→ ส่งต่อ ผอ.]
```

---

## 7. ไฟล์และหน้าที่ในระบบ Workflow

| ไฟล์ | บทบาท | Input | Output |
|---|---|---|---|
| `sarabun_manage.php` | แสดงรายการหนังสือทั้งหมด | Filter GET | ตาราง + ปุ่ม action |
| `sarabun_sign.php` | UI ลงรับ (ตราปั้ม) | doc_id, file_id, page | Form + PDF Canvas |
| `sarabun_signtxt.php` | UI เกษียณ (ข้อความ+ลายเซ็น) | doc_id, file_id, page | Form + PDF Canvas |
| `sarabun_generate_1.php` | บันทึกตราปั้มลงรับ | POST form | INSERT sign_doc, sign_detail |
| `sarabun_generate_2.php` | บันทึกเกษียณ (สารบรรณ) | POST form | INSERT/UPDATE sign_doc, sign_detail |
| `codirector_manage.php` | รายการสำหรับรอง ผอ. | dep_id filter | ตาราง + ปุ่ม action |
| `codirector_sign.php` | UI ลงนามรอง ผอ. | doc_id, file_id, page | Form + PDF Canvas |
| `director_manage.php` | รายการสำหรับ ผอ. | sign_codirector=approve | ตาราง + ปุ่ม action |
| `director_sign.php` | UI ลงนาม ผอ. | doc_id, file_id, page | Form + PDF Canvas |
| `sarabun_generate_3.php` | บันทึก annotation รอง ผอ./ผอ. | POST form | UPDATE sign_codirector หรือ sign_director |
| `document_preview.php` | Preview PDF + Annotation (อ่านอย่างเดียว) | doc_id, file_id, page | PDF Canvas + Annotation |

---

## 8. ปัญหาที่พบจากการวิเคราะห์ Workflow

### 🔴 Bug ที่อาจเกิดขึ้น

| ปัญหา | ไฟล์ | สาเหตุ |
|---|---|---|
| รอง ผอ. ทุกฝ่ายเห็นหนังสือได้ถ้าไม่ได้ระบุ dep_id | `sarabun_generate_1.php` | ลงรับ (ฟอร์ม 1) ไม่บันทึก dep_id ใน sign_doc |
| sarabun_generate_3.php ใช้ร่วมกันทั้ง รอง ผอ. และ ผอ. | ทั้งคู่ | Logic ต่างกันแต่ใช้ไฟล์เดียวกัน อาจสับสน |
| ผอ. เห็นเอกสารที่รอง ผอ. ยังไม่ลงนาม | `director_manage.php` | Filter ถูกต้องแล้ว แต่ถ้า sign_codirector skip ได้ผ่าน |
| ลงนามซ้ำได้ (ไม่มีการ lock หลัง approve) | sign_detail | ไม่มีการตรวจสอบว่า sign แล้วหรือยัง |

### 🟡 จุดที่ควรปรับปรุง

| รายการ | เหตุผล |
|---|---|
| ปุ่ม "ลงรับ" ถูก disable เมื่อ `doc_status=approve` แต่ logic ใน PHP ไม่ตรงกัน | ปุ่มยัง active ถ้าไม่มี record ใน sign_doc |
| ไม่มีการแจ้งเตือนเมื่อหนังสือถูกส่งถึงฝ่าย | รอง ผอ.ต้อง refresh หน้าเองตลอด |
| `sarabun_generate_3.php` redirect กลับ sarabun_manage.php เสมอ | ควร redirect กลับหน้าที่เหมาะสมตาม role |

---

## 9. แผนพัฒนา Workflow ต่อ

### ✅ ด่วน: แก้ Bug
- [ ] `sarabun_generate_1.php` ต้องบันทึก `dep_id` ใน `sign_doc` เมื่อสารบรรณเลือกฝ่ายปลายทาง
- [ ] แยก generate script ของ รอง ผอ. และ ผอ. ออกจากกัน

### 🔧 ปรับปรุง Workflow
- [ ] เพิ่ม `status` badge บน Dashboard แสดงจำนวนหนังสือรอดำเนินการ
- [ ] เพิ่มการ lock annotation หลัง approve แล้ว (ป้องกันการแก้ไข)
- [ ] เพิ่ม history timeline ของแต่ละ document (join sign_detail กับ user)
- [ ] Email/Notification เมื่อหนังสือถูกส่งมาถึงฝ่ายตัวเอง

### 🚀 ใหม่
- [ ] สร้าง PDF จริง (embed annotation ลงในไฟล์ PDF ถาวร) ด้วย mPDF แทนการ overlay CSS
- [ ] QR Code ตรวจสอบสถานะ workflow บนเอกสาร

---

*วิเคราะห์จากโค้ดจริงทุกไฟล์ ณ วันที่ 25 กุมภาพันธ์ 2569*
