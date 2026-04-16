import { useMemo, useRef } from 'react';
import ReactQuill from 'react-quill';
import 'react-quill/dist/quill.snow.css';
import { uploadApiRequest } from '../utils/api';

const FORMATS = ['header', 'bold', 'italic', 'underline', 'blockquote', 'list', 'bullet', 'link', 'align', 'image'];

function escapeHtmlAttribute(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

export default function RichTextEditor({ value, onChange, token }) {
  const quillRef = useRef(null);

  const modules = useMemo(
    () => ({
      toolbar: {
        container: [
          [{ header: [2, 3, 4, 5, 6, false] }],
          [{ align: [] }],
          ['bold', 'italic', 'underline', 'blockquote'],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['link'],
          ['imageCenter', 'imageLeft', 'imageRight'],
          ['clean'],
        ],
        handlers: {
          imageCenter: () => {
            void insertImage('center');
          },
          imageLeft: () => {
            void insertImage('left');
          },
          imageRight: () => {
            void insertImage('right');
          },
        },
      },
    }),
    []
  );

  async function insertImage(alignment) {
    if (!token) {
      return;
    }

    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.accept = 'image/jpeg,image/png,image/webp,image/gif';
    fileInput.onchange = async (event) => {
      const selectedFile = event.target?.files?.[0];
      if (!selectedFile) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('image', selectedFile);

        const response = await uploadApiRequest('/dashboard/uploads/image', {
          method: 'POST',
          body: formData,
          token,
        });

        const quill = quillRef.current?.getEditor();
        if (!quill) {
          return;
        }

        const range = quill.getSelection(true);
        const index = range?.index ?? quill.getLength();
        const alignmentClass =
          alignment === 'left' ? 'rich-image--left' : alignment === 'right' ? 'rich-image--right' : 'rich-image--center';
        const safeUrl = escapeHtmlAttribute(response.image_url_absolute || response.image_url || '');

        quill.clipboard.dangerouslyPasteHTML(
          index,
          `<p class="rich-image ${alignmentClass}"><img src="${safeUrl}" alt="" /></p><p><br></p>`
        );
        quill.setSelection(index + 2, 0, 'silent');
      } catch (error) {
        // Keep feedback lightweight to avoid introducing more dashboard state wiring.
        window.alert(error?.message || 'Image upload failed.');
      }
    };

    fileInput.click();
  }

  return <ReactQuill ref={quillRef} theme="snow" value={value} onChange={onChange} modules={modules} formats={FORMATS} />;
}
