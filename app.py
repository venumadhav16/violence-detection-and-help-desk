import os
import cv2
import numpy as np
import tensorflow as tf
import threading 
from tensorflow.keras.models import load_model
import mediapipe as mp
from deepface import DeepFace
import requests
import time
import logging
from datetime import datetime
from flask import Flask, render_template, request, jsonify, redirect, url_for, flash
from werkzeug.utils import secure_filename

# Create required directories
for directory in ['logs', 'uploads', 'models', 'snapshots']:
    if not os.path.exists(directory):
        os.makedirs(directory)

# Configure logging
def setup_logger():
    formatter = logging.Formatter('%(asctime)s | %(levelname)s | %(message)s', datefmt='%Y-%m-%d %H:%M:%S')
    log_file = os.path.join('logs', f'violence_detection_{datetime.now().strftime("%Y%m%d")}.log')
    file_handler = logging.FileHandler(log_file)
    file_handler.setFormatter(formatter)
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger = logging.getLogger('ViolenceDetection')
    logger.setLevel(logging.INFO)
    logger.handlers = []
    logger.addHandler(file_handler)
    logger.addHandler(console_handler)
    return logger

# Initialize Flask app and constants
app = Flask(__name__)
app.config['UPLOAD_FOLDER'] = 'uploads'
app.config['MAX_CONTENT_LENGTH'] = 16 * 1024 * 1024
app.secret_key = "violence_detection_secret_key"
logger = setup_logger()

# Constants
DANGEROUS_OBJECTS = ['knife', 'scissors', 'gun', 'baseball bat', 'stick']
OBJECT_CONFIDENCE_THRESHOLD = 0.4
VIOLENCE_THRESHOLD = 0.75
VIOLENT_POSE_THRESHOLD = 0.6
GROUP_SIZE_THRESHOLD = 3
EMOTION_DISTRESS_THRESHOLD = 60
PROXIMITY_THRESHOLD = 0.15
ALERT_COOLDOWN = 15  # seconds
BOT_TOKEN = "7554098622:AAFoi1HwfBoZQNu5nBghbt1cTsJd3456DlA"
CHAT_ID = "@BB16PROJECT"

def load_models():
    """Load all required models for detection"""
    try:
        logger.info("Loading all models...")
        models = {}
        
        # Load violence detection model
        models['violence'] = load_model("final_violence_detection.h5")
        
        # Load YOLOv3 for object detection (replacing YOLOv8)
        models['net'] = cv2.dnn.readNetFromDarknet('yolov3.cfg', 'yolov3.weights')
        with open('coco.names', 'r') as f:
            models['classes'] = f.read().strip().split('\n')
        
        # Load gender classification model
        models['gender'] = load_model("gender_classification_model.h5")
        
        # Initialize MediaPipe components
        mp_pose = mp.solutions.pose
        mp_hands = mp.solutions.hands
        mp_face_detection = mp.solutions.face_detection
        
        models['pose'] = mp_pose.Pose(min_detection_confidence=0.7, min_tracking_confidence=0.7)
        models['hands'] = mp_hands.Hands(min_detection_confidence=0.7, min_tracking_confidence=0.7)
        models['face_detection'] = mp_face_detection.FaceDetection(min_detection_confidence=0.7)
        
        logger.info("All models loaded successfully")
        return models
        
    except Exception as e:
        logger.error(f"Error loading models: {str(e)}")
        return None

def detect_gender(face_img, gender_model):
    """Unified gender detection function"""
    try:
        resized = cv2.resize(face_img, (224, 224))
        normalized = resized / 255.0
        preprocessed = np.expand_dims(normalized, axis=0)
        
        prediction = gender_model.predict(preprocessed)
        gender = "Woman" if prediction[0][0] > 0.7 else "Man"
        confidence = prediction[0][0] if gender == "Woman" else 1 - prediction[0][0]
        
        # Only return gender if confidence is high
        return gender if confidence > 0.6 else None
    except Exception as e:
        logger.error(f"Gender detection error: {str(e)}")
        return None

def detect_dangerous_objects_yolov3(frame, models):
    """Detect dangerous objects using YOLOv3"""
    try:
        # Get image dimensions
        height, width = frame.shape[:2]
        
        # Create blob from image
        blob = cv2.dnn.blobFromImage(frame, 1/255.0, (416, 416), swapRB=True, crop=False)
        models['net'].setInput(blob)
        
        # Get output layer names
        layer_names = models['net'].getLayerNames()
        output_layers = [layer_names[i - 1] for i in models['net'].getUnconnectedOutLayers()]
        
        # Run forward pass
        outputs = models['net'].forward(output_layers)
        
        # Process detections
        dangerous_detections = []
        
        for output in outputs:
            for detection in output:
                scores = detection[5:]
                class_id = np.argmax(scores)
                confidence = scores[class_id]
                
                if confidence > OBJECT_CONFIDENCE_THRESHOLD:
                    class_name = models['classes'][class_id].lower()
                    
                    if class_name in DANGEROUS_OBJECTS:
                        # Scale coordinates to original image
                        center_x = int(detection[0] * width)
                        center_y = int(detection[1] * height)
                        w = int(detection[2] * width)
                        h = int(detection[3] * height)
                        
                        # Calculate bounding box coordinates
                        x = int(center_x - w / 2)
                        y = int(center_y - h / 2)
                        
                        dangerous_detections.append({
                            'name': class_name,
                            'confidence': float(confidence),
                            'bbox': (x, y, x + w, y + h)
                        })
        
        return dangerous_detections
    except Exception as e:
        logger.error(f"YOLOv3 detection error: {str(e)}")
        return []

def detect_gesture_and_emotion(rgb_frame, frame, models):
    """Combined function for detecting thumbs down gestures and fear emotion"""
    try:
        results = {
            'thumbs_down': False,
            'distressed_females': []
        }
        
        # Detect faces
        face_results = models['face_detection'].process(rgb_frame)
        if not face_results.detections:
            return results
        
        # Process hands for thumbs down gesture
        hand_results = models['hands'].process(rgb_frame)
        if hand_results.multi_hand_landmarks:
            for hand_landmarks in hand_results.multi_hand_landmarks:
                mp_hands = mp.solutions.hands
                thumb_tip = hand_landmarks.landmark[mp_hands.HandLandmark.THUMB_TIP]
                thumb_mcp = hand_landmarks.landmark[mp_hands.HandLandmark.THUMB_MCP]
                middle_finger_tip = hand_landmarks.landmark[mp_hands.HandLandmark.MIDDLE_FINGER_TIP]
                
                # Check for thumbs down gesture
                if (thumb_tip.y > thumb_mcp.y) and (thumb_tip.y > middle_finger_tip.y):
                    results['thumbs_down'] = True
                    break
        
        # If thumbs down detected, check for distressed females
        if results['thumbs_down']:
            # Process all detected faces
            for i, detection in enumerate(face_results.detections):
                bboxC = detection.location_data.relative_bounding_box
                ih, iw, _ = frame.shape
                x, y, w, h = int(bboxC.xmin * iw), int(bboxC.ymin * ih), \
                             int(bboxC.width * iw), int(bboxC.height * ih)
                
                # Ensure valid face crop
                if x < 0 or y < 0 or x+w > iw or y+h > ih or w <= 0 or h <= 0:
                    continue
                    
                face_img = frame[y:y+h, x:x+w]
                if face_img.size == 0:
                    continue
                
                # Check gender
                gender = detect_gender(face_img, models['gender'])
                if gender != 'Woman':
                    continue
                
                # Check for fear emotion
                try:
                    analysis = DeepFace.analyze(face_img, actions=['emotion'], enforce_detection=False)
                    emotions = analysis[0]['emotion'] if isinstance(analysis, list) else analysis['emotion']
                    fear_score = emotions.get('fear', 0)
                    
                    if fear_score > EMOTION_DISTRESS_THRESHOLD:
                        results['distressed_females'].append({
                            'location': (x, y, x+w, y+h),
                            'fear_score': fear_score
                        })
                except Exception as emotion_error:
                    logger.debug(f"Emotion detection failed for face: {str(emotion_error)}")
        
        return results
    except Exception as e:
        logger.error(f"Gesture and emotion detection error: {str(e)}")
        return {'thumbs_down': False, 'distressed_females': []}

def analyze_poses(poses_detected, mp_pose):
    """Unified pose analysis function that returns both individual and group violence indicators"""
    try:
        if len(poses_detected) < 1:
            return {
                'has_violent_poses': False, 
                'violent_poses_desc': "",
                'has_mass_violence': False,
                'mass_violence_desc': ""
            }
        
        # Initialize results
        results = {
            'has_violent_poses': False,
            'violent_poses_desc': "",
            'has_mass_violence': False,
            'mass_violence_desc': "",
            'raised_hands_count': 0,
            'violent_indicators': []
        }
        
        # First, count aggressive poses and raised hands
        for i, pose1 in enumerate(poses_detected):
            if not pose1.pose_landmarks:
                continue
                
            # Get key landmarks
            left_wrist = pose1.pose_landmarks.landmark[mp_pose.PoseLandmark.LEFT_WRIST]
            right_wrist = pose1.pose_landmarks.landmark[mp_pose.PoseLandmark.RIGHT_WRIST]
            left_shoulder = pose1.pose_landmarks.landmark[mp_pose.PoseLandmark.LEFT_SHOULDER]
            right_shoulder = pose1.pose_landmarks.landmark[mp_pose.PoseLandmark.RIGHT_SHOULDER]
            nose = pose1.pose_landmarks.landmark[mp_pose.PoseLandmark.NOSE]
            
            # Check for raised hands
            hands_raised = left_wrist.y < left_shoulder.y and right_wrist.y < right_shoulder.y
            if hands_raised:
                results['raised_hands_count'] += 1
                
                # Check for hands near face (potential fighting pose)
                hands_near_face = (abs(left_wrist.x - nose.x) < 0.2 or abs(right_wrist.x - nose.x) < 0.2)
                if hands_near_face:
                    results['violent_indicators'].append(f"Person {i+1} showing fighting stance")
            
            # Check proximity between people
            for j, pose2 in enumerate(poses_detected[i+1:], i+1):
                if not pose2.pose_landmarks:
                    continue
                    
                nose2 = pose2.pose_landmarks.landmark[mp_pose.PoseLandmark.NOSE]
                
                # Calculate proximity
                distance = np.sqrt((nose.x - nose2.x)**2 + (nose.y - nose2.y)**2)
                
                # Check for close proximity with raised hands
                if distance < PROXIMITY_THRESHOLD and hands_raised:
                    results['violent_indicators'].append(f"Close confrontation between Person {i+1} and Person {j+1}")
        
        # Determine if there's violence between individuals
        results['has_violent_poses'] = len(results['violent_indicators']) > 0
        results['violent_poses_desc'] = "; ".join(results['violent_indicators'])
        
        # Determine if there's mass gathering violence
        if len(poses_detected) >= GROUP_SIZE_THRESHOLD:
            violence_percentage = (results['raised_hands_count'] / len(poses_detected)) * 100
            results['has_mass_violence'] = (results['raised_hands_count'] >= (GROUP_SIZE_THRESHOLD - 1) 
                                        and violence_percentage > 60)
            
            if results['has_mass_violence']:
                results['mass_violence_desc'] = (f"Mass gathering with {results['raised_hands_count']} of "
                                             f"{len(poses_detected)} people showing aggressive poses "
                                             f"({violence_percentage:.1f}%)")
        
        return results
    except Exception as e:
        logger.error(f"Pose analysis error: {str(e)}")
        return {
            'has_violent_poses': False, 
            'violent_poses_desc': "",
            'has_mass_violence': False,
            'mass_violence_desc': ""
        }

def send_alert_with_snapshot(frame, alert_type, description="", detections=None):
    """Combined function to save snapshot and send Telegram alert"""
    try:
        current_time = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Determine incident type and create message
        if alert_type == "weapons":
            weapons_found = ", ".join([f"{det['name']} ({det['confidence']:.2f})" for det in detections])
            message = f"⚠️ HIGH CONFIDENCE DANGER - {current_time}: Dangerous objects detected: {weapons_found}!"
            incident_type = "Dangerous_Objects"
        elif alert_type == "mass_gathering":
            message = f"⚠️ HIGH CONFIDENCE ALERT - {current_time}: Mass gathering violence detected!\nDetails: {description}"
            incident_type = "Mass_Gathering_Violence"
        elif alert_type == "female_distress":
            message = f"🚨 HIGH CONFIDENCE FEMALE DISTRESS - {current_time}!\nDetails: {description}"
            incident_type = "Female_Distress"
        elif alert_type == "violent_poses":
            message = f"⚠️ HIGH CONFIDENCE ALERT - {current_time}: Multiple people showing violent behavior!\nDetails: {description}"
            incident_type = "Violent_Poses"
        elif alert_type == "violence":
            message = f"🚨 VIOLENCE DETECTED - {current_time}!\nDetails: {description}"
            incident_type = "Violence_Detection"
        else:
            logger.warning(f"Unknown alert type: {alert_type}")
            return
        
        # Create directory for this incident type if it doesn't exist
        incident_dir = os.path.join("snapshots", incident_type)
        if not os.path.exists(incident_dir):
            os.makedirs(incident_dir)
        
        # Prepare filename
        incident_type_clean = incident_type.replace(" ", "_")
        filename = f"{timestamp}_{incident_type_clean}.jpg"
        filepath = os.path.join(incident_dir, filename)
        
        # Add text to frame
        frame_with_info = frame.copy()
        cv2.putText(frame_with_info, f"Time: {timestamp}", (10, 30), 
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        cv2.putText(frame_with_info, f"Type: {incident_type}", (10, 60), 
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
        
        # Add description in multiple lines if needed
        if description:
            words = description.split()
            lines = []
            current_line = []
            for word in words:
                current_line.append(word)
                if len(' '.join(current_line)) > 40:
                    lines.append(' '.join(current_line[:-1]))
                    current_line = [word]
            if current_line:
                lines.append(' '.join(current_line))
                
            for i, line in enumerate(lines):
                cv2.putText(frame_with_info, line, (10, 90 + i*30), 
                           cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 2)
        
        # Save image and details
        cv2.imwrite(filepath, frame_with_info)
        
        txt_filepath = filepath.replace('.jpg', '_details.txt')
        with open(txt_filepath, 'w') as f:
            f.write(f"Incident Details:\n")
            f.write(f"Timestamp: {timestamp}\n")
            f.write(f"Type: {incident_type}\n")
            f.write(f"Description: {description}\n")
        
        # Send combined alert with photo and message
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendPhoto"
        with open(filepath, "rb") as image:
            response = requests.post(
                url, 
                data={"chat_id": CHAT_ID, "caption": message},
                files={"photo": image}
            )
            
        if response.status_code != 200:
            logger.error(f"Telegram alert failed with status code {response.status_code}: {response.text}")
        else:
            logger.info(f"Telegram alert sent: {alert_type}")
            
        return filepath
    except Exception as e:
        logger.error(f"Error sending alert: {str(e)}")
        return None

def process_video_stream(source):
    """Main function to process video from various sources"""
    try:
        # Load all required models
        models = load_models()
        if not models:
            logger.error("Failed to load required models")
            return False
            
        # Generate a unique session ID
        session_id = datetime.now().strftime("%Y%m%d_%H%M%S")
        logger.info(f"Starting video processing session: {session_id}")
        
        # Open video capture
        cap = cv2.VideoCapture(source)
        if not cap.isOpened():
            logger.error(f"Could not open video source: {source}")
            return False
            
        # Initialize variables
        frame_count = 0
        last_alert_time = {}
        violence_buffer = []
        buffer_size = 15
        
        # Get MediaPipe reference for easier access
        mp_pose = mp.solutions.pose
        
        # Main processing loop
        while True:
            ret, frame = cap.read()
            if not ret:
                logger.info("End of video stream reached")
                break
                
            frame_count += 1
            if frame_count < 15:  # Reduced from 30 for faster startup
                continue
                
            # Get current time
            current_time = datetime.now().strftime('%H:%M:%S')
            
            # Convert frame to RGB for MediaPipe
            rgb_frame = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            
            # Display current time on video feed
            cv2.putText(frame, f"Time: {current_time}", (10, 30), 
                        cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)
            
            # 1. Detect dangerous objects (highest priority)
            dangerous_objects = detect_dangerous_objects_yolov3(frame, models)
            if dangerous_objects:
                if 'weapons' not in last_alert_time or (datetime.now() - last_alert_time['weapons']).total_seconds() > ALERT_COOLDOWN:
                    # Draw bounding boxes
                    for det in dangerous_objects:
                        x1, y1, x2, y2 = det['bbox']
                        name = det['name']
                        conf = det['confidence']
                        cv2.rectangle(frame, (x1, y1), (x2, y2), (0, 0, 255), 2)
                        cv2.putText(frame, f"{name}: {conf:.2f}", (x1, y1-10), 
                                   cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 255), 2)
                    
                    send_alert_with_snapshot(frame, "weapons", detections=dangerous_objects)
                    last_alert_time['weapons'] = datetime.now()
                    cv2.putText(frame, "ALERT: DANGEROUS OBJECT DETECTED!", 
                               (50, 70), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
            
            # 2. Violence detection using ML model
            # Preprocess the frame
            try:
                blurred_frame = cv2.GaussianBlur(frame, (5, 5), 0)
                resized_frame = cv2.resize(blurred_frame, (224, 224))
                normalized_frame = resized_frame / 255.0
                preprocessed = np.expand_dims(normalized_frame, axis=0)
                
                # Predict violence
                violence_score = float(models['violence'].predict(preprocessed, verbose=0)[0][0])
                violence_buffer.append(violence_score)
                if len(violence_buffer) > buffer_size:
                    violence_buffer.pop(0)
                    
                avg_violence_score = sum(violence_buffer) / len(violence_buffer)
                if avg_violence_score > VIOLENCE_THRESHOLD:
                    if 'violence' not in last_alert_time or (datetime.now() - last_alert_time['violence']).total_seconds() > ALERT_COOLDOWN:
                        description = f"Violence detected with confidence {avg_violence_score:.2f}"
                        send_alert_with_snapshot(frame, "violence", description)
                        last_alert_time['violence'] = datetime.now()
                        cv2.putText(frame, f"ALERT: VIOLENCE DETECTED! Score: {avg_violence_score:.2f}", 
                                   (50, 100), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
            except Exception as violence_error:
                logger.error(f"Violence detection processing error: {str(violence_error)}")
            
            # Collect all poses for analysis - use more efficient approach
            pose_results = []
            temp_pose = mp_pose.Pose(min_detection_confidence=0.6, min_tracking_confidence=0.6)
            result = temp_pose.process(rgb_frame)
            if result.pose_landmarks:
                pose_results.append(result)
                
                # Try to detect additional poses in one pass with lower threshold
                temp_pose2 = mp_pose.Pose(min_detection_confidence=0.5, min_tracking_confidence=0.5)
                result2 = temp_pose2.process(rgb_frame)
                if result2.pose_landmarks and not np.array_equal(
                        [lm.x for lm in result.pose_landmarks.landmark[:5]], 
                        [lm.x for lm in result2.pose_landmarks.landmark[:5]]):
                    pose_results.append(result2)
                temp_pose2.close()
            temp_pose.close()
            
            # 3 & 5. Combined pose analysis (mass gathering + violent poses)
            if pose_results:
                pose_analysis = analyze_poses(pose_results, mp_pose)
                
                # Check for mass gathering violence
                if pose_analysis['has_mass_violence']:
                    if 'mass_gathering' not in last_alert_time or (datetime.now() - last_alert_time['mass_gathering']).total_seconds() > ALERT_COOLDOWN:
                        send_alert_with_snapshot(frame, "mass_gathering", pose_analysis['mass_violence_desc'])
                        last_alert_time['mass_gathering'] = datetime.now()
                        cv2.putText(frame, "ALERT: MASS GATHERING VIOLENCE DETECTED!", 
                                   (50, 130), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
                
                # Check for violent poses between individuals
                if pose_analysis['has_violent_poses']:
                    if 'violent_poses' not in last_alert_time or (datetime.now() - last_alert_time['violent_poses']).total_seconds() > ALERT_COOLDOWN:
                        send_alert_with_snapshot(frame, "violent_poses", pose_analysis['violent_poses_desc'])
                        last_alert_time['violent_poses'] = datetime.now()
                        cv2.putText(frame, "ALERT: VIOLENT BEHAVIOR DETECTED!", 
                                   (50, 160), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
            
            # 4. Detect female distress with combined function
            gesture_emotion_results = detect_gesture_and_emotion(rgb_frame, frame, models)
            if gesture_emotion_results['thumbs_down'] and gesture_emotion_results['distressed_females']:
                # Count males around distressed female
                male_count = 0
                ih, iw, _ = frame.shape
                
                # Process for each distressed female
                for female_info in gesture_emotion_results['distressed_females']:
                    fx, fy, fx2, fy2 = female_info['location']
                    fcx, fcy = (fx + fx2) // 2, (fy + fy2) // 2
                    
                    # Detect faces to count males
                    face_results = models['face_detection'].process(rgb_frame)
                    if face_results.detections:
                        for detection in face_results.detections:
                            bboxC = detection.location_data.relative_bounding_box
                            x, y, w, h = int(bboxC.xmin * iw), int(bboxC.ymin * ih), \
                                         int(bboxC.width * iw), int(bboxC.height * ih)
                            
                            # Skip invalid faces or the female herself
                            if x < 0 or y < 0 or x+w > iw or y+h > ih or w <= 0 or h <= 0:
                                continue
                            if abs(x - fx) < 10 and abs(y - fy) < 10:  # Same person
                                continue
                                
                            # Check proximity
                            mcx, mcy = (x + x+w) // 2, (y + y+h) // 2
                            distance = np.sqrt((fcx - mcx)**2 + (fcy - mcy)**2)
                            
                            if distance < (PROXIMITY_THRESHOLD * iw):
                                face_img = frame[y:y+h, x:x+w]
                                if face_img.size == 0:
                                    continue
                                    
                                gender = detect_gender(face_img, models['gender'])
                                if gender == 'Man':
                                    male_count += 1
                    
                    # Alert if sufficient males around distressed female
                    if male_count >= 2:
                        if 'female_distress' not in last_alert_time or (datetime.now() - last_alert_time['female_distress']).total_seconds() > ALERT_COOLDOWN:
                            description = f"Female showing thumbs down and fear expression surrounded by {male_count} males"
                            send_alert_with_snapshot(frame, "female_distress", description)
                            last_alert_time['female_distress'] = datetime.now()
                            cv2.putText(frame, "ALERT: FEMALE DISTRESS DETECTED!", 
                                       (50, 190), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)
                            break  # Only alert once
            
            # Display the current frame
            cv2.imshow("Violence Detection System", frame)
            if cv2.waitKey(1) & 0xFF == ord('q'):
                break
                
        # Clean up
        cap.release()
        cv2.destroyAllWindows()
        for model_key in ['pose', 'hands', 'face_detection']:
            if model_key in models:
                models[model_key].close()
        
        logger.info(f"Video processing session {session_id} completed successfully")
        return True
        
    except Exception as e:
        logger.error(f"Error in video processing: {str(e)}")
        logger.error(traceback.format_exc())
        return False

@app.route('/', methods=['GET', 'POST'])
def index():
    """Handle both GET and POST requests for the main page"""
    if request.method == 'POST':
        try:
            # Handle CCTV URL
            cctv_url = request.form.get('cctv_url')
            if cctv_url and cctv_url.strip():
                detection_thread = threading.Thread(target=process_video_stream, args=(cctv_url,))
                detection_thread.daemon = True
                detection_thread.start()
                flash('Started processing CCTV stream', 'success')
                return redirect(url_for('index'))

            # Handle file upload
            if 'file' in request.files:
                video = request.files['file']
                if video and video.filename:
                    filename = secure_filename(video.filename)
                    filepath = os.path.join(app.config['UPLOAD_FOLDER'], filename)
                    video.save(filepath)
                    detection_thread = threading.Thread(target=process_video_stream, args=(filepath,))
                    detection_thread.daemon = True
                    detection_thread.start()
                    flash('Video uploaded and processing started!', 'success')
                    return redirect(url_for('index'))

            # Handle inbuilt camera
            use_camera = request.form.get('use_camera') == 'true'
            if use_camera:
                detection_thread = threading.Thread(target=process_video_stream, args=(0,))
                detection_thread.daemon = True
                detection_thread.start()
                flash('Started processing webcam feed', 'success')
                return redirect(url_for('index'))

            flash('Please provide a video source (CCTV URL, file, or enable camera)', 'error')
            return redirect(url_for('index'))

        except Exception as e:
            logger.error(f"Error processing request: {str(e)}")
            flash(f'Error: {str(e)}', 'error')
            return redirect(url_for('index'))

    # GET request - render template
    return render_template('index.html')

@app.route('/get_incidents')
def get_incidents():
    """Get list of detected incidents"""
    try:
        incidents = []
        for incident_type in os.listdir('snapshots'):
            type_dir = os.path.join('snapshots', incident_type)
            if os.path.isdir(type_dir):
                for filename in os.listdir(type_dir):
                    if filename.endswith('.jpg'):
                        details_file = os.path.join(type_dir, filename.replace('.jpg', '_details.txt'))
                        details = {}
                        if os.path.exists(details_file):
                            with open(details_file, 'r') as f:
                                for line in f:
                                    if ':' in line:
                                        key, value = line.strip().split(':', 1)
                                        details[key.strip()] = value.strip()
                        
                        incidents.append({
                            'type': incident_type,
                            'timestamp': details.get('Timestamp', ''),
                            'description': details.get('Description', ''),
                            'image_path': os.path.join('snapshots', incident_type, filename)
                        })
                        
        return jsonify({'incidents': incidents})
        
    except Exception as e:
        logger.error(f"Error getting incidents: {str(e)}")
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    try:
        logger.info("Starting Violence Detection System...")
        app.run(host='0.0.0.0', port=5000, debug=False)
    except Exception as e:
        logger.error(f"Failed to start application: {str(e)}")