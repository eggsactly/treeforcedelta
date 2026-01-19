CREATE TABLE uploaded_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    upload_date DATETIME NOT NULL,
    CONSTRAINT fk_uploaded_images_event
        FOREIGN KEY (event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
);

