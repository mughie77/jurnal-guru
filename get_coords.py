from PIL import Image

img = Image.open('/tmp/file_attachments/karpel.jpg')
width, height = img.size
print(f"Original size: {width}x{height}")

# Target display width
target_width = 600
scale = target_width / width
target_height = int(height * scale)
print(f"Scaled size: {target_width}x{target_height}")

# Let's try to detect the white boxes or use visual estimation if I could see the image.
# Since I can't "see" pixels directly with code easily without opencv/logic,
# I will use the 184x230 dimensions mentioned in the image for the photo.

photo_width_scaled = 184 * scale
photo_height_scaled = 230 * scale
print(f"Photo scaled: {photo_width_scaled}x{photo_height_scaled}")
