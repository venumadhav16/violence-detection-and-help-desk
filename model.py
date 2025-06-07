import tensorflow as tf
from tensorflow.keras.models import Sequential
from tensorflow.keras.layers import Conv2D, MaxPooling2D, Dense, Flatten, Dropout
from tensorflow.keras.preprocessing.image import ImageDataGenerator
import os
# Set random seed for reproducibility
tf.random.set_seed(42)

# Constants
IMG_HEIGHT = 224
IMG_WIDTH = 224
BATCH_SIZE = 32
EPOCHS = 20

def create_data_generators(data_dir):
    # Data augmentation for training
    train_datagen = ImageDataGenerator(
        rescale=1./255,
        rotation_range=20,
        width_shift_range=0.2,
        height_shift_range=0.2,
        shear_range=0.2,
        zoom_range=0.2,
        horizontal_flip=True,
        validation_split=0.2
    )

    # Only rescaling for validation
    valid_datagen = ImageDataGenerator(
        rescale=1./255,
        validation_split=0.2
    )

    # Training generator
    train_generator = train_datagen.flow_from_directory(
        data_dir,
        target_size=(IMG_HEIGHT, IMG_WIDTH),
        batch_size=BATCH_SIZE,
        class_mode='binary',
        subset='training'
    )

    # Validation generator
    validation_generator = valid_datagen.flow_from_directory(
        data_dir,
        target_size=(IMG_HEIGHT, IMG_WIDTH),
        batch_size=BATCH_SIZE,
        class_mode='binary',
        subset='validation'
    )

    return train_generator, validation_generator

def create_model():
    model = Sequential([
        # First Convolutional Block
        Conv2D(32, 3, activation='relu', input_shape=(IMG_HEIGHT, IMG_WIDTH, 3)),
        MaxPooling2D(),
        
        # Second Convolutional Block
        Conv2D(64, 3, activation='relu'),
        MaxPooling2D(),
        
        # Third Convolutional Block
        Conv2D(64, 3, activation='relu'),
        MaxPooling2D(),
        
        # Fourth Convolutional Block
        Conv2D(128, 3, activation='relu'),
        MaxPooling2D(),
        
        # Flatten and Dense Layers
        Flatten(),
        Dense(128, activation='relu'),
        Dropout(0.5),
        Dense(64, activation='relu'),
        Dropout(0.3),
        Dense(1, activation='sigmoid')  # Binary classification (violence/non-violence)
    ])

    # Compile the model
    model.compile(
        optimizer='adam',
        loss='binary_crossentropy',
        metrics=['accuracy']
    )

    return model

def train_model(data_dir):
    # Create data generators
    train_generator, validation_generator = create_data_generators(data_dir)
    
    # Create and compile the model
    model = create_model()
    
    # Model summary
    model.summary()
    
    # Train the model
    history = model.fit(
        train_generator,
        epochs=EPOCHS,
        validation_data=validation_generator
    )
    
    # Save the model
    model.save('final_violence_detection_model.h5')
    
    return history, model



# Main execution
if __name__ == "__main__":
    # Specify your dataset directory
    data_dir = 'C:/Users/venum/OneDrive/Desktop/dataset'
    
    # Train the model
    history, model = train_model(data_dir)