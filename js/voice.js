import { state, VAD_CONFIG, MIC_GAIN } from "./config.js";
import {
  getSelectedLanguage,
  getSpeechLang,
  pickVoice,
  resolveTtsLanguage,
} from "./language.js";
import { sendMessage, submitVoiceComplaint } from "./api.js";
import { normalizeTranscription } from "./utils.js";
import { autoCapture } from "./camera.js";
import { setComplaintStatus, typewriterEffect } from "./ui.js";

function spellNumberUzbek(numberStr) {
  let num = parseInt(numberStr.replace(/[^\d\-]/g, ""), 10);
  if (isNaN(num)) return numberStr;
  if (num === 0) return "nol";

  let isNegative = num < 0;
  num = Math.abs(num);

  const units = ["", "ming", "million", "milliard", "trillion"];
  const ones = ["", "bir", "ikki", "uch", "to'rt", "besh", "olti", "yetti", "sakkiz", "to'qqiz"];
  const tens = ["", "o'n", "yigirma", "o'ttiz", "qirq", "ellik", "oltmish", "yetmish", "sakson", "to'qson"];

  let str = "";
  let unitIdx = 0;

  while (num > 0) {
    let chunk = num % 1000;
    num = Math.floor(num / 1000);

    if (chunk > 0) {
      let chunkStr = "";
      let h = Math.floor(chunk / 100);
      let t = Math.floor((chunk % 100) / 10);
      let o = chunk % 10;

      if (h > 0) chunkStr += ones[h] + " yuz ";
      if (t > 0) chunkStr += tens[t] + " ";
      if (o > 0) chunkStr += ones[o] + " ";

      str = chunkStr.trim() + " " + units[unitIdx] + " " + str;
    }
    unitIdx++;
  }

  let res = str.trim();
  if (isNegative) res = "minus " + res;
  return res;
}

export function prepareTtsText(text, language = 'uz') {
  if (!text) return text;
  const letterMap = {
    A: "ay",
    B: "bee",
    C: "see",
    D: "dee",
    E: "ee",
    F: "ef",
    G: "gee",
    H: "aitch",
    I: "eye",
    J: "jay",
    K: "kay",
    L: "el",
    M: "em",
    N: "en",
    O: "oh",
    P: "pee",
    Q: "cue",
    R: "ar",
    S: "ess",
    T: "tee",
    U: "you",
    V: "vee",
    W: "double u",
    X: "ex",
    Y: "why",
    Z: "zee",
  };
  
  // 0. Emoji va maxsus belgilarni o'chirish (🌤 🎤 ✅ kabi belgilar o'qilmasin)
  let processed = text.replace(/[\u{1F000}-\u{1FFFF}]|[\u{2600}-\u{27BF}]|[\u{2300}-\u{23FF}]|[\u{2B00}-\u{2BFF}]/gu, '');

  // 0.1 Remove bracketed metadata tags (failsafe)
  processed = processed.replace(/\[.*?\]/g, '').replace(/\[[A-Z0-9_:]+[^\]]*$/g, '');

  // 0.2 Remove markdown formatting
  processed = processed.replace(/\*\*/g, '').replace(/^-\s*/gm, '');
  
  // Belgi "/" umuman o'qilmasligi uchun uni bo'sh joyga almashtiramiz
  processed = processed.replace(/\//g, ' ');

  // Telefon raqamlardagi yoki boshqa o'rindagi tire (-) belgisi "minus" deb o'qilmasligi uchun
  // faqat raqamlar o'rtasida kelgan chiziqchalarni bo'sh joyga almashtiramiz.
  processed = processed.replace(/(\d)-(\d)/g, '$1 $2');
  processed = processed.replace(/(\d)-(\d)/g, '$1 $2'); // Ikki marta, chunki 29-14-11 kabi ustma-ust tushishi mumkin

  // Telefon raqam formatlari "95 232 24 24" ni aniqlab o'zbekcha o'qish
  if (language === 'uz') {
    processed = processed.replace(/\b(\d{2})[- ]+(\d{3})[- ]+(\d{2})[- ]+(\d{2})\b/g, (m, p1, p2, p3, p4) => {
      return `${spellNumberUzbek(p1)} ${spellNumberUzbek(p2)} ${spellNumberUzbek(p3)} ${spellNumberUzbek(p4)}`;
    });
  }
  
  // 1. Remove bracketed IATA codes
  processed = processed.replace(/\s*\([A-Z]{3}\)(ga|ni|da|dan)?/g, "$1");

  // 2. Format Times based on language
  if (language === 'uz') {
    // "15:30 da" → "o'n beshdan o'ttiz daqiqa o'tganda"
    processed = processed.replace(/(\d{1,2}):(\d{2})\s*da/g, (m, h, mnt) => {
      const hWord = spellNumberUzbek(h);
      if (mnt === "00") return `${hWord}da`;
      const mWord = spellNumberUzbek(mnt);
      return `${hWord}dan ${mWord} daqiqa o'tganda`;
    });
    // "17:00" (no suffix) → "o'n yettida nol nolda"
    processed = processed.replace(/(\d{1,2}):(\d{2})/g, (m, h, mnt) => {
      const hWord = spellNumberUzbek(h);
      if (mnt === "00") return `${hWord}da nol nolda`;
      const mWord = spellNumberUzbek(mnt);
      return `${hWord}da ${mWord}da`;
    });
  } else if (language === 'ru') {
    processed = processed.replace(/(\d{1,2}):(\d{2})/g, (m, h, mnt) => {
      return `${parseInt(h, 10)} ${parseInt(mnt, 10)}`;
    });
  } else {
    processed = processed.replace(/(\d{1,2}):(\d{2})/g, (m, h, mnt) => {
      const hStr = spellNumberUzbek(parseInt(h));
      const mStr = mnt === "00" ? "nol nol" : spellNumberUzbek(parseInt(mnt));
      return `${hStr} ${mStr}`;
    });
  }

  // 3. Format Gates: "B1 darvoza" -> "B 1-chi darvoza"
  processed = processed.replace(
    /\b([A-Z])(\d{1,3})([A-Z]?)\s*darvoza/gi,
    (m, letter, num, suffix) => {
      let result = `${letter.toUpperCase()} ${num}-chi`;
      if (suffix) {
        result += ` ${suffix.toUpperCase()}`;
      }
      return `${result} darvoza`;
    },
  );

  // 4. Parvoz kodlarini (HY, TK, HH) ingliz tilida harfma-harf, biroz to'xtab (cho'zib) o'qish
  const flightRegex = /\b([A-Z][A-Z0-9]|[A-Z0-9][A-Z]|[A-Z]{3})\s*(\d{1,4})\b/ig;
  processed = processed.replace(flightRegex, (m, code, num) => {
    const upperCode = code.toUpperCase();
    const spelledCode = upperCode
      .split("")
      .map((ch) => letterMap[ch] || ch)
      .join(", "); // Harflar orasiga vergul qo'yish orqali TTS biroz cho'zib, to'xtab o'qiydi
    return `${spelledCode}, ${num}`;
  });

  // 5. Katta raqamlarni (valyutalar: -170 000, 1.000.000) so'zga o'girish
  if (language === 'uz') {
    const numRegex = /(-?)\b([1-9]\d{0,2}(?:(?:[\.\s,]\d{3})+)|\d{4,12})\b/g;
    processed = processed.replace(numRegex, (match) => {
      return spellNumberUzbek(match);
    });
  }

  return processed;
}

export function resetTtsPlayback() {
  state.ttsAbort = true;
  if (state.currentAudio) {
    state.currentAudio.pause();
    state.currentAudio.currentTime = 0;
    state.currentAudio = null;
  }
  if (state.currentAudioSource) {
    try {
      state.currentAudioSource.disconnect();
    } catch (e) {}
    state.currentAudioSource = null;
  }
  if (state.currentAudioUrl) {
    URL.revokeObjectURL(state.currentAudioUrl);
    state.currentAudioUrl = null;
  }
}

export function base64ToBlob(base64, mime = "audio/mpeg") {
  const binary = atob(base64);
  const len = binary.length;
  const bytes = new Uint8Array(len);
  for (let i = 0; i < len; i++) bytes[i] = binary.charCodeAt(i);
  return new Blob([bytes], { type: mime });
}

export function playAudioBlob(blob) {
  return new Promise((resolve, reject) => {
    if (state.ttsAbort) return resolve();

    state.currentAudioUrl = URL.createObjectURL(blob);
    state.currentAudio = new Audio(state.currentAudioUrl);
    state.currentAudio.crossOrigin = "anonymous";

    if (!state.audioContext)
      state.audioContext = new (
        window.AudioContext || window.webkitAudioContext
      )();
    if (!state.outputAnalyser) {
      state.outputAnalyser = state.audioContext.createAnalyser();
      state.outputAnalyser.fftSize = 256;
      state.outputDataArray = new Uint8Array(
        state.outputAnalyser.frequencyBinCount,
      );
    }

    try {
      state.currentAudioSource = state.audioContext.createMediaElementSource(
        state.currentAudio,
      );
      state.currentAudioSource.connect(state.outputAnalyser);
      state.outputAnalyser.connect(state.audioContext.destination);
    } catch (e) {
      state.currentAudioSource = null;
    }

    state.currentAudio.onended = () => {
      if (state.currentAudioUrl) URL.revokeObjectURL(state.currentAudioUrl);
      state.currentAudio = null;
      state.currentAudioUrl = null;
      resolve();
    };
    state.currentAudio.onerror = (e) => reject(e);
    state.currentAudio.play().catch((e) => reject(e));
  });
}

export async function playAudioQueue(chunks) {
  let playedCount = 0;
  for (const chunk of chunks) {
    if (state.ttsAbort) break;
    playedCount++;
    const blob = base64ToBlob(chunk);
    try {
      await playAudioBlob(blob);
    } catch (e) {
      console.error(`Chunk failed:`, e);
      break;
    }
  }
}

export function fallbackSpeech(text, language = "uz") {
  resetTtsPlayback();
  window.speechSynthesis.cancel();
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = getSpeechLang(language);
  const preferred = pickVoice(language);
  if (preferred) utterance.voice = preferred;
  window.speechSynthesis.speak(utterance);
}

export async function speakGemini(text, voiceName = "Aoede") {
  try {
    const response = await fetch("api/gemini_voice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ text: text, voice_name: voiceName }),
    });
    const data = await response.json();
    if (data.success && data.audioContent) {
      const mime = data.mimeType || "audio/wav";
      const blob = base64ToBlob(data.audioContent, mime);
      await playAudioBlob(blob);
      return true;
    }
    throw new Error(data.error || "Gemini audio yo'q");
  } catch (err) {
    console.warn("⚠️ Gemini TTS Failed:", err);
    return false;
  }
}

/**
 * Gemini SSE Streaming TTS — real-time audio playback
 * Har bir jumla tayyor bo'lganda darhol ijro etadi (prefetch pattern)
 */
export function speakGeminiStream(text, voiceName = "Aoede") {
  return new Promise((resolve, reject) => {
    console.log("🔊 Gemini SSE Streaming TTS ishga tushdi...");

    const audioQueue = [];
    let isPlaying = false;
    let streamDone = false;
    let chunksReceived = 0;
    let chunksPlayed = 0;
    let totalExpected = 0;

    // Fetch-based SSE (POST so'rovni qo'llab-quvvatlaydi)
    async function startStream() {
      try {
        const response = await fetch("api/gemini_stream_tts.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ text, voice_name: voiceName }),
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = "";

        while (true) {
          if (state.ttsAbort) {
            reader.cancel();
            break;
          }

          const { done, value } = await reader.read();
          if (done) break;

          buffer += decoder.decode(value, { stream: true });

          // SSE formatini parse qilish — qo'sh newline bilan ajratish
          while (true) {
            const eventEnd = buffer.indexOf("\n\n");
            if (eventEnd === -1) break;

            const eventBlock = buffer.substring(0, eventEnd);
            buffer = buffer.substring(eventEnd + 2);

            let eventName = "";
            let eventData = "";
            for (const line of eventBlock.split("\n")) {
              if (line.startsWith("event: ")) {
                eventName = line.slice(7).trim();
              } else if (line.startsWith("data: ")) {
                eventData = line.slice(6);
              }
            }

            if (eventName && eventData) {
              try {
                const data = JSON.parse(eventData);
                handleSSEEvent(eventName, data);
              } catch (e) {
                // JSON parse error — skip
              }
            }
          }
        }

        streamDone = true;
        checkFinished();
      } catch (err) {
        console.error("SSE Stream error:", err);
        streamDone = true;
        if (chunksReceived === 0) {
          reject(err);
        } else {
          checkFinished();
        }
      }
    }

    function handleSSEEvent(event, data) {
      if (event === "start") {
        totalExpected = data.totalChunks || 0;
        console.log(`🎬 TTS boshlanmoqda: ${totalExpected} chunk, ovoz: ${data.voice}`);
      } else if (event === "chunk" && data.audio) {
        chunksReceived++;
        const blob = base64ToBlob(data.audio, data.mime || "audio/wav");
        audioQueue.push(blob);
        console.log(
          `🎵 Chunk ${data.index + 1}/${data.total} keldi` +
            (data.cached ? " (cache)" : "") +
            ` — "${data.text}"`
        );
        // Agar hozir o'ynalmayotgan bo'lsa — darhol boshlash
        if (!isPlaying) {
          playNext();
        }
      } else if (event === "chunk_error") {
        console.warn(`⚠️ Chunk ${data.index + 1}/${data.total} xato:`, data.error);
        // Xato chunklarni o'tkazib yuborish — playback davom etadi
      } else if (event === "done") {
        streamDone = true;
        console.log(`✅ Stream tugadi: ${data.totalChunks} chunk`);
        checkFinished();
      } else if (event === "error") {
        console.error("❌ SSE error:", data.message);
        streamDone = true;
        checkFinished();
      }
    }

    function checkFinished() {
      if (streamDone && !isPlaying && audioQueue.length === 0) {
        console.log(`🏁 TTS playback tugadi: ${chunksPlayed} chunk o'ynaldi`);
        resolve();
      }
    }

    async function playNext() {
      if (state.ttsAbort) {
        resolve();
        return;
      }

      if (isPlaying) return;

      if (audioQueue.length === 0) {
        checkFinished();
        return;
      }

      isPlaying = true;
      const blob = audioQueue.shift();

      try {
        await playAudioBlob(blob);
        chunksPlayed++;
      } catch (e) {
        console.error("Audio playback error:", e);
      }

      isPlaying = false;

      // Keyingi chunk bor bo'lsa davom etish
      if (audioQueue.length > 0) {
        playNext();
      } else {
        checkFinished();
      }
    }

    startStream();
  });
}

export async function speakText(text, language = "uz") {
  if (!text) return;
  const ttsText = prepareTtsText(text, language);
  const ttsLang = resolveTtsLanguage(language, ttsText);

  if (ttsLang !== "uz") {
    fallbackSpeech(ttsText, ttsLang);
    return;
  }

  resetTtsPlayback();
  window.speechSynthesis.cancel();
  state.ttsAbort = false;

  // 1. Asosiy: Gemini SSE Streaming TTS (barqaror)
  if (language === "uz") {
    try {
      console.log("🔊 Gemini SSE Streaming TTS ishga tushiraman...");
      await speakGeminiStream(ttsText, "Aoede");
      if (!state.ttsAbort) return;
    } catch (err) {
      console.warn("⚠️ Gemini SSE Streaming Failed, trying single Gemini:", err);
    }
  }

  // 3. Oxirgi Chora: Gemini Aoede (eski)
  if (language === "uz") {
    const success = await speakGemini(ttsText, "Aoede");
    if (success) return;
  }
}

export function stopAssistantVoice() {
  state.ttsAbort = true;
  resetTtsPlayback();
  window.speechSynthesis.cancel();
}

export function toggleAssistantVoice() {
  if (state.currentAudio) {
    if (state.currentAudio.paused) {
      state.currentAudio.play();
    } else {
      state.currentAudio.pause();
    }
  }
}

let audioContextSource;
let processor;
let pcmData = [];
let sampleRate = 0;

export async function startRecording() {
  if (state.isRecording) return;
  state.isRecording = true;
  const btnVoice = document.getElementById("btn-voice");
  if (btnVoice) btnVoice.classList.add("active-recording");
  const assistantTextElement = document.getElementById("assistant-text");
  if (assistantTextElement)
    assistantTextElement.innerText = "Sizni eshityapman...";

  try {
    autoCapture(true);
    const stream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
        channelCount: 1,
        sampleRate: 48000,
      },
    });

    state.inputAudioContext = new (
      window.AudioContext || window.webkitAudioContext
    )();
    sampleRate = state.inputAudioContext.sampleRate;
    audioContextSource =
      state.inputAudioContext.createMediaStreamSource(stream);
    state.micHighpass = state.inputAudioContext.createBiquadFilter();
    state.micHighpass.type = "highpass";
    state.micHighpass.frequency.value = 120;

    state.micCompressor = state.inputAudioContext.createDynamicsCompressor();
    state.micCompressor.threshold.value = -30;
    state.micCompressor.knee.value = 24;
    state.micCompressor.ratio.value = 3;
    state.micCompressor.attack.value = 0.003;
    state.micCompressor.release.value = 0.25;

    state.micGainNode = state.inputAudioContext.createGain();
    state.micGainNode.gain.value = MIC_GAIN;

    audioContextSource
      .connect(state.micHighpass)
      .connect(state.micCompressor)
      .connect(state.micGainNode);

    try {
      await state.inputAudioContext.audioWorklet.addModule(
        "recorder-worklet.js",
      );
      processor = new AudioWorkletNode(
        state.inputAudioContext,
        "recorder-worklet",
      );
      pcmData = [];
      processor.port.onmessage = (e) => {
        pcmData.push(e.data);
      };
      state.micGainNode.connect(processor);
      processor.connect(state.inputAudioContext.destination);
    } catch (e) {
      console.warn("AudioWorklet yuklanmadi, eski usulda davom etiladi:", e);
      processor = state.inputAudioContext.createScriptProcessor(4096, 1, 1);
      processor.onaudioprocess = (e) => {
        const input = e.inputBuffer.getChannelData(0);
        pcmData.push(new Float32Array(input));
      };
      state.micGainNode.connect(processor);
      processor.connect(state.inputAudioContext.destination);
    }

    state.mediaRecorder = {
      state: "recording",
      stream: stream,
      stop: () => {
        if (processor) {
          state.mediaRecorder.state = "inactive";
          processor.disconnect();
          audioContextSource.disconnect();
          if (state.micGainNode) state.micGainNode.disconnect();
          if (state.micHighpass) state.micHighpass.disconnect();
          if (state.micCompressor) state.micCompressor.disconnect();
          if (
            state.inputAudioContext &&
            state.inputAudioContext.state !== "closed"
          )
            state.inputAudioContext.close();
          state.inputAudioContext = null;
          state.micGainNode = null;
          state.micHighpass = null;
          state.micCompressor = null;
          onRecordingStop();
        }
      },
    };

    startVAD(state.inputAudioContext, state.micGainNode);
  } catch (err) {
    console.error("Recording error:", err);
    state.isRecording = false;
    if (btnVoice) btnVoice.classList.remove("active-recording");
    if (assistantTextElement)
      assistantTextElement.innerText = "Mikrofonga ruxsat berilmagan.";
  }
}

export function stopRecording() {
  if (state.mediaRecorder && state.mediaRecorder.state !== "inactive") {
    state.mediaRecorder.stop();
    if (state.mediaRecorder.stream) {
      state.mediaRecorder.stream.getTracks().forEach((track) => track.stop());
    }
  }
}

async function onRecordingStop() {
  const mergedPcm = mergeBuffers(pcmData);
  const downsampledPcm = downsampleBuffer(mergedPcm, sampleRate, 16000);
  const wavBlob = encodeWAV(downsampledPcm, 16000);

  const formData = new FormData();
  formData.append("audio", wavBlob, "recording.wav");
  formData.append("language", getSelectedLanguage());

  const assistantTextElement = document.getElementById("assistant-text");
  if (assistantTextElement)
    assistantTextElement.innerText = "Tahlil qilyapman...";
  try {
    const response = await fetch("api/stt.php", {
      method: "POST",
      body: formData,
    });
    const data = await response.json();
    const transcription = data.text || data.result || data.transcript;
    const normalized = normalizeTranscription(transcription);

    if (normalized && normalized.length >= 2) {
      if (assistantTextElement) {
        typewriterEffect(assistantTextElement, normalized);
      }
      console.log("🎤 STT Eshitdi:", normalized);
      sendMessage(normalized);
      autoCapture();
    } else {
      if (assistantTextElement)
        assistantTextElement.innerText =
          "Gapni tushuna olmadim, qaytadan urinib ko'ring.";
    }
  } catch (err) {
    console.error("STT Error:", err);
    if (assistantTextElement)
      assistantTextElement.innerText = "Xizmatda xatolik yuz berdi.";
  }
  state.isRecording = false;
  const btnVoice = document.getElementById("btn-voice");
  if (btnVoice) btnVoice.classList.remove("active-recording");
}

function mergeBuffers(buffers) {
  let length = 0;
  buffers.forEach((b) => (length += b.length));
  const result = new Float32Array(length);
  let offset = 0;
  buffers.forEach((b) => {
    result.set(b, offset);
    offset += b.length;
  });
  return result;
}

function encodeWAV(samples, sampleRate) {
  const buffer = new ArrayBuffer(44 + samples.length * 2);
  const view = new DataView(buffer);
  writeString(view, 0, "RIFF");
  view.setUint32(4, 32 + samples.length * 2, true);
  writeString(view, 8, "WAVE");
  writeString(view, 12, "fmt ");
  view.setUint32(16, 16, true);
  view.setUint16(20, 1, true);
  view.setUint16(22, 1, true);
  view.setUint32(24, sampleRate, true);
  view.setUint32(28, sampleRate * 2, true);
  view.setUint16(32, 2, true);
  view.setUint16(34, 16, true);
  writeString(view, 36, "data");
  view.setUint32(40, samples.length * 2, true);
  floatTo16BitPCM(view, 44, samples);
  return new Blob([view], { type: "audio/wav" });
}

function writeString(view, offset, string) {
  for (let i = 0; i < string.length; i++)
    view.setUint8(offset + i, string.charCodeAt(i));
}

function floatTo16BitPCM(output, offset, input) {
  for (let i = 0; i < input.length; i++, offset += 2) {
    const s = Math.max(-1, Math.min(1, input[i]));
    output.setInt16(offset, s < 0 ? s * 0x8000 : s * 0x7fff, true);
  }
}

function downsampleBuffer(buffer, inputSampleRate, outputSampleRate) {
  if (inputSampleRate === outputSampleRate) return buffer;
  const sampleRateRatio = inputSampleRate / outputSampleRate;
  const newLength = Math.round(buffer.length / sampleRateRatio);
  const result = new Float32Array(newLength);
  let offsetResult = 0;
  let offsetBuffer = 0;
  while (offsetResult < result.length) {
    const nextOffsetBuffer = Math.round((offsetResult + 1) * sampleRateRatio);
    let accum = 0;
    let count = 0;
    for (let i = offsetBuffer; i < nextOffsetBuffer && i < buffer.length; i++) {
      accum += buffer[i];
      count++;
    }
    result[offsetResult] = accum / count;
    offsetResult++;
    offsetBuffer = nextOffsetBuffer;
  }
  return result;
}

function startVAD(context, sourceNode) {
  if (!context || !sourceNode) return;
  const analyzer = context.createAnalyser();
  sourceNode.connect(analyzer);

  analyzer.fftSize = VAD_CONFIG.fftSize;
  const dataArray = new Uint8Array(analyzer.fftSize);

  let noiseFloor = VAD_CONFIG.minRms;
  let threshold = VAD_CONFIG.minRms * VAD_CONFIG.thresholdFactor;
  const warmupUntil = performance.now() + VAD_CONFIG.warmupMs;
  let lastSpeechAt = performance.now();
  let hasSpeech = false;
  let smoothedRms = 0;

  const checkVolume = () => {
    analyzer.getByteTimeDomainData(dataArray);
    let sum = 0;
    for (let i = 0; i < dataArray.length; i++) {
      const v = (dataArray[i] - 128) / 128;
      sum += v * v;
    }
    const rmsRaw = Math.sqrt(sum / dataArray.length);
    smoothedRms =
      smoothedRms === 0
        ? rmsRaw
        : smoothedRms * VAD_CONFIG.smoothFactor +
          rmsRaw * (1 - VAD_CONFIG.smoothFactor);
    const now = performance.now();

    if (now < warmupUntil) {
      noiseFloor = noiseFloor * 0.9 + smoothedRms * 0.1;
      threshold = Math.max(
        VAD_CONFIG.minRms,
        noiseFloor * VAD_CONFIG.thresholdFactor,
      );
    }

    if (smoothedRms > threshold) {
      hasSpeech = true;
      lastSpeechAt = now;
    } else if (hasSpeech) {
      const silenceFor = now - lastSpeechAt;
      if (silenceFor >= VAD_CONFIG.silenceMs) {
        stopRecording();
        return;
      }
    }

    if (state.isRecording) requestAnimationFrame(checkVolume);
  };
  checkVolume();
}

export async function startComplaintRecording() {
  if (state.isComplaintRecording) return;
  if (state.isRecording) stopRecording();

  const btn = document.getElementById("btn-complaint-record");
  try {
    state.complaintStream = await navigator.mediaDevices.getUserMedia({
      audio: {
        echoCancellation: true,
        noiseSuppression: true,
        autoGainControl: true,
      },
    });

    const options = {};
    if (
      window.MediaRecorder &&
      MediaRecorder.isTypeSupported("audio/webm;codecs=opus")
    ) {
      options.mimeType = "audio/webm;codecs=opus";
    }
    state.complaintRecorder = new MediaRecorder(state.complaintStream, options);
    state.complaintChunks = [];
    state.isComplaintRecording = true;

    state.complaintRecorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) state.complaintChunks.push(e.data);
    };

    state.complaintRecorder.onstop = async () => {
      const mime =
        state.complaintRecorder && state.complaintRecorder.mimeType
          ? state.complaintRecorder.mimeType
          : "audio/webm";
      const blob = new Blob(state.complaintChunks, { type: mime });
      const preview = document.getElementById("complaint-audio-preview");
      if (preview && blob.size > 0) {
        preview.src = URL.createObjectURL(blob);
        preview.style.display = "block";
      }

      if (state.complaintStream) {
        state.complaintStream.getTracks().forEach((t) => t.stop());
      }
      state.complaintStream = null;
      state.complaintRecorder = null;
      state.complaintChunks = [];
      state.isComplaintRecording = false;
      if (btn) {
        btn.textContent = "OVOZLI SHIKOYATNI BOSHLASH";
        btn.classList.remove("active-recording");
      }

      await submitVoiceComplaint(blob);
    };

    state.complaintRecorder.start(250);
    if (btn) {
      btn.textContent = "YOZUVNI YAKUNLASH VA YUBORISH";
      btn.classList.add("active-recording");
    }
    setComplaintStatus("Yozuv ketmoqda... Tugmani qayta bosib yuboring.");
  } catch (e) {
    console.error("Complaint recording error:", e);
    state.isComplaintRecording = false;
    setComplaintStatus("Mikrofonga ruxsat berilmadi.", true);
  }
}

export function stopComplaintRecording() {
  if (!state.isComplaintRecording || !state.complaintRecorder) return;
  try {
    state.complaintRecorder.stop();
  } catch (e) {
    console.error("Complaint recorder stop error:", e);
  }
}
