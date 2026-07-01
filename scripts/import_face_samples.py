import os
import json
import math
import hashlib
from pathlib import Path

import cv2
import numpy as np
import mysql.connector
import tensorflow as tf


# =========================
# PROJECT PATHS
# =========================

BACKEND_ROOT = Path(r"C:\xampp\htdocs\facetrack_backend")

SAMPLE_FACES_DIR = BACKEND_ROOT / "sample_faces"
MODEL_PATH = BACKEND_ROOT / "models" / "mobilefacenet.tflite"

# Stored in MySQL as relative web path
SAMPLE_FACE_WEB_PREFIX = "sample_faces"


# =========================
# MYSQL CONFIG
# =========================

DB_CONFIG = {
    "host": "127.0.0.1",
    "port": 3307,
    "user": "root",
    "password": "",
    "database": "facetrack_ai",
}


# =========================
# RECOGNITION SETTINGS
# =========================

FACE_MATCH_THRESHOLD = 0.88

VALID_EXTENSIONS = {".jpg", ".jpeg", ".png", ".webp"}


# =========================
# BASIC HELPERS
# =========================

def normalize_embedding(embedding):
    embedding = np.asarray(embedding, dtype=np.float32)
    norm = np.linalg.norm(embedding)

    if norm == 0:
        return embedding.tolist()

    return (embedding / norm).tolist()


def cosine_similarity(a, b):
    a = np.asarray(a, dtype=np.float32)
    b = np.asarray(b, dtype=np.float32)

    if a.shape != b.shape:
        return 0.0

    norm_a = np.linalg.norm(a)
    norm_b = np.linalg.norm(b)

    if norm_a == 0 or norm_b == 0:
        return 0.0

    return float(np.dot(a, b) / (norm_a * norm_b))


def parse_embedding(text):
    if text is None:
        return None

    text = str(text).strip()

    if not text:
        return None

    try:
        decoded = json.loads(text)

        if not isinstance(decoded, list):
            return None

        embedding = [float(x) for x in decoded]

        if len(embedding) < 64:
            return None

        return normalize_embedding(embedding)
    except Exception:
        return None


def file_sha256(file_path):
    sha = hashlib.sha256()

    with open(file_path, "rb") as file:
        for chunk in iter(lambda: file.read(8192), b""):
            sha.update(chunk)

    return sha.hexdigest()


def get_relative_web_path(image_path):
    image_path = Path(image_path)
    roll_no = image_path.parent.name
    file_name = image_path.name

    return f"{SAMPLE_FACE_WEB_PREFIX}/{roll_no}/{file_name}".replace("\\", "/")


# =========================
# FACE DETECTION + CROP
# =========================

def detect_largest_face(image_bgr):
    gray = cv2.cvtColor(image_bgr, cv2.COLOR_BGR2GRAY)

    cascade_path = cv2.data.haarcascades + "haarcascade_frontalface_default.xml"

    detector = cv2.CascadeClassifier(cascade_path)

    faces = detector.detectMultiScale(
        gray,
        scaleFactor=1.08,
        minNeighbors=5,
        minSize=(55, 55),
    )

    if len(faces) == 0:
        return None

    # Choose largest detected face
    faces = sorted(faces, key=lambda box: box[2] * box[3], reverse=True)

    return faces[0]


def crop_face_with_margin(image_bgr, face_box, margin_x_ratio=0.28, margin_y_ratio=0.35):
    x, y, w, h = face_box

    image_height, image_width = image_bgr.shape[:2]

    margin_x = int(w * margin_x_ratio)
    margin_y = int(h * margin_y_ratio)

    left = max(0, x - margin_x)
    top = max(0, y - margin_y)
    right = min(image_width, x + w + margin_x)
    bottom = min(image_height, y + h + margin_y)

    crop = image_bgr[top:bottom, left:right]

    if crop.size == 0:
        return None

    return crop


def enhance_face_crop(face_bgr):
    # Resize before model input for better consistency
    face_bgr = cv2.resize(face_bgr, (160, 160), interpolation=cv2.INTER_CUBIC)

    # Slight contrast/brightness enhancement
    alpha = 1.12
    beta = 5

    enhanced = cv2.convertScaleAbs(face_bgr, alpha=alpha, beta=beta)

    return enhanced


# =========================
# MOBILEFACENET EMBEDDING
# =========================

def load_tflite_model():
    if not MODEL_PATH.exists():
        raise FileNotFoundError(f"Model not found: {MODEL_PATH}")

    interpreter = tf.lite.Interpreter(model_path=str(MODEL_PATH))
    interpreter.allocate_tensors()

    return interpreter


def run_mobilefacenet(interpreter, face_bgr):
    face_bgr = cv2.resize(face_bgr, (112, 112), interpolation=cv2.INTER_CUBIC)

    # Convert BGR to RGB because Flutter image package reads RGB order
    face_rgb = cv2.cvtColor(face_bgr, cv2.COLOR_BGR2RGB)

    input_data = (face_rgb.astype(np.float32) - 127.5) / 128.0
    input_data = np.expand_dims(input_data, axis=0)

    input_details = interpreter.get_input_details()
    output_details = interpreter.get_output_details()

    input_index = input_details[0]["index"]
    input_dtype = input_details[0]["dtype"]

    if input_dtype == np.uint8:
        scale, zero_point = input_details[0]["quantization"]
        if scale == 0:
            input_data = input_data.astype(np.uint8)
        else:
            input_data = (input_data / scale + zero_point).astype(np.uint8)
    else:
        input_data = input_data.astype(np.float32)

    interpreter.set_tensor(input_index, input_data)
    interpreter.invoke()

    output = interpreter.get_tensor(output_details[0]["index"])
    embedding = output.flatten().astype(np.float32)

    return normalize_embedding(embedding)


def generate_embedding_for_image(interpreter, image_path):
    image_bgr = cv2.imread(str(image_path))

    if image_bgr is None:
        return None, "Could not read image"

    face_box = detect_largest_face(image_bgr)

    if face_box is None:
        return None, "No clear front/slight-angle face detected"

    embeddings = []

    crop_settings = [
        (0.14, 0.18),
        (0.22, 0.28),
        (0.32, 0.40),
    ]

    for margin_x, margin_y in crop_settings:
        crop = crop_face_with_margin(
            image_bgr,
            face_box,
            margin_x_ratio=margin_x,
            margin_y_ratio=margin_y,
        )

        if crop is None:
            continue

        enhanced = enhance_face_crop(crop)

        normal_embedding = run_mobilefacenet(interpreter, enhanced)
        embeddings.append(normal_embedding)

        flipped = cv2.flip(enhanced, 1)
        flipped_embedding = run_mobilefacenet(interpreter, flipped)
        embeddings.append(flipped_embedding)

    if not embeddings:
        return None, "Embedding generation failed"

    averaged = np.mean(np.asarray(embeddings, dtype=np.float32), axis=0)

    return normalize_embedding(averaged), "OK"


# =========================
# MYSQL HELPERS
# =========================

def get_connection():
    return mysql.connector.connect(**DB_CONFIG)


def get_student(cursor, roll_no):
    cursor.execute(
        """
        SELECT roll_no, name, face_registered, face_embedding
        FROM students
        WHERE roll_no = %s
        LIMIT 1
        """,
        (roll_no,),
    )

    return cursor.fetchone()


def get_existing_sample_count(cursor, roll_no):
    cursor.execute(
        """
        SELECT COUNT(*) AS sample_count
        FROM student_face_embeddings
        WHERE roll_no = %s
        """,
        (roll_no,),
    )

    row = cursor.fetchone()

    return int(row["sample_count"] or 0)


def sample_hash_exists_for_same_student(cursor, roll_no, face_hash):
    cursor.execute(
        """
        SELECT id
        FROM student_face_embeddings
        WHERE roll_no = %s AND face_hash = %s
        LIMIT 1
        """,
        (roll_no, face_hash),
    )

    return cursor.fetchone() is not None


def get_other_student_embeddings(cursor, current_roll_no):
    cursor.execute(
        """
        SELECT 
            s.roll_no,
            s.name,
            s.face_hash,
            s.face_embedding,
            e.face_hash AS sample_face_hash,
            e.face_embedding AS sample_face_embedding
        FROM students s
        LEFT JOIN student_face_embeddings e
        ON s.roll_no = e.roll_no
        WHERE s.roll_no != %s
        AND (
            s.face_registered = 1
            OR s.face_embedding IS NOT NULL
            OR e.face_embedding IS NOT NULL
        )
        """,
        (current_roll_no,),
    )

    return cursor.fetchall()


def check_face_used_by_other_student(cursor, roll_no, face_hash, new_embedding):
    rows = get_other_student_embeddings(cursor, roll_no)

    best_similarity = 0.0
    best_match = None

    for row in rows:
        existing_roll = row["roll_no"]
        existing_name = row["name"]

        main_hash = str(row["face_hash"] or "").strip()
        sample_hash = str(row["sample_face_hash"] or "").strip()

        if main_hash and main_hash == face_hash:
            return True, 1.0, {
                "roll_no": existing_roll,
                "name": existing_name,
                "source": "main_image_hash",
            }

        if sample_hash and sample_hash == face_hash:
            return True, 1.0, {
                "roll_no": existing_roll,
                "name": existing_name,
                "source": "sample_image_hash",
            }

        main_embedding = parse_embedding(row["face_embedding"])
        sample_embedding = parse_embedding(row["sample_face_embedding"])

        if main_embedding is not None:
            similarity = cosine_similarity(new_embedding, main_embedding)

            if similarity > best_similarity:
                best_similarity = similarity
                best_match = {
                    "roll_no": existing_roll,
                    "name": existing_name,
                    "source": "main_face",
                }

        if sample_embedding is not None:
            similarity = cosine_similarity(new_embedding, sample_embedding)

            if similarity > best_similarity:
                best_similarity = similarity
                best_match = {
                    "roll_no": existing_roll,
                    "name": existing_name,
                    "source": "sample_face",
                }

    if best_similarity >= FACE_MATCH_THRESHOLD:
        return True, best_similarity, best_match

    return False, best_similarity, best_match


def insert_sample(cursor, roll_no, student_name, image_path, face_hash, embedding, sample_label):
    relative_path = get_relative_web_path(image_path)

    cursor.execute(
        """
        INSERT INTO student_face_embeddings (
            roll_no,
            student_name,
            face_image,
            face_hash,
            face_embedding,
            face_embedding_model,
            sample_label
        ) VALUES (
            %s,
            %s,
            %s,
            %s,
            %s,
            'MobileFaceNet',
            %s
        )
        """,
        (
            roll_no,
            student_name,
            relative_path,
            face_hash,
            json.dumps(embedding),
            sample_label,
        ),
    )


def update_student_registration_status(cursor, roll_no):
    # Does NOT update profile image or main face image
    cursor.execute(
        """
        UPDATE students
        SET 
            face_registered = 1,
            face_status = 'Registered',
            face_embedding_model = 'MobileFaceNet',
            face_registered_at = COALESCE(face_registered_at, NOW())
        WHERE roll_no = %s
        """,
        (roll_no,),
    )


# =========================
# IMPORT PROCESS
# =========================

def import_samples():
    if not SAMPLE_FACES_DIR.exists():
        raise FileNotFoundError(f"sample_faces folder not found: {SAMPLE_FACES_DIR}")

    interpreter = load_tflite_model()
    connection = get_connection()
    cursor = connection.cursor(dictionary=True)

    inserted = 0
    skipped = 0
    failed = 0

    print("\n========== Face Sample Import Started ==========\n")

    for student_folder in sorted(SAMPLE_FACES_DIR.iterdir()):
        if not student_folder.is_dir():
            continue

        roll_no = student_folder.name.strip()

        student = get_student(cursor, roll_no)

        if student is None:
            print(f"[SKIP] Folder {roll_no}: No student found in database")
            skipped += 1
            continue

        student_name = student["name"]

        image_files = [
            file for file in sorted(student_folder.iterdir())
            if file.suffix.lower() in VALID_EXTENSIONS
        ]

        if not image_files:
            print(f"[SKIP] {roll_no} {student_name}: No images found")
            skipped += 1
            continue

        print(f"\nStudent: {student_name} ({roll_no})")
        print(f"Images found: {len(image_files)}")

        for image_path in image_files:
            try:
                face_hash = file_sha256(image_path)

                if sample_hash_exists_for_same_student(cursor, roll_no, face_hash):
                    print(f"  [SKIP] {image_path.name}: Duplicate sample for same student")
                    skipped += 1
                    continue

                embedding, status = generate_embedding_for_image(interpreter, image_path)

                if embedding is None:
                    print(f"  [FAIL] {image_path.name}: {status}")
                    failed += 1
                    continue

                used_by_other, best_similarity, best_match = check_face_used_by_other_student(
                    cursor,
                    roll_no,
                    face_hash,
                    embedding,
                )

                if used_by_other:
                    match_text = "Unknown"

                    if best_match is not None:
                        match_text = f"{best_match['name']} ({best_match['roll_no']}) from {best_match['source']}"

                    print(
                        f"  [SKIP] {image_path.name}: Face seems already used by another student "
                        f"similarity={best_similarity:.4f} match={match_text}"
                    )
                    skipped += 1
                    continue

                next_sample_number = get_existing_sample_count(cursor, roll_no) + 1
                sample_label = f"Face Sample {next_sample_number}"

                insert_sample(
                    cursor=cursor,
                    roll_no=roll_no,
                    student_name=student_name,
                    image_path=image_path,
                    face_hash=face_hash,
                    embedding=embedding,
                    sample_label=sample_label,
                )

                update_student_registration_status(cursor, roll_no)

                connection.commit()

                print(
                    f"  [OK] {image_path.name}: {sample_label} inserted "
                    f"best_other_similarity={best_similarity:.4f}"
                )

                inserted += 1

            except Exception as error:
                connection.rollback()
                print(f"  [ERROR] {image_path.name}: {error}")
                failed += 1

    cursor.close()
    connection.close()

    print("\n========== Import Finished ==========")
    print(f"Inserted: {inserted}")
    print(f"Skipped : {skipped}")
    print(f"Failed  : {failed}")
    print("=====================================\n")


if __name__ == "__main__":
    import_samples()