# ใช้ PHP เวอร์ชันล่าสุด
FROM php:8.2-cli

# ตั้ง working directory
WORKDIR /app

# คัดลอกไฟล์ทั้งหมดเข้า container
COPY . /app

# เปิดพอร์ตให้ render ใช้
EXPOSE 10000

# สั่งให้ PHP รันเว็บด้วย index.php
CMD ["php", "-S", "0.0.0.0:10000", "index.php"]
