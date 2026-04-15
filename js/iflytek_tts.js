/**
 * iFlytek TTS Frontend Module
 * handles direct WebSocket connection for audio synthesis
 */

import { state } from "./config.js";

const IFLYTEK_VOICES = [
    { vcn: "x2_UzUz_Nilufar", name: "Nilufar (Uzbek)", gender: "Ayol", info: "O'zbek tili uchun asosiy ovoz" },
    { vcn: "xiaoyan", name: "Standard (Multilingual)", gender: "Ayol", info: "Ko'p tilli ovoz" },
    { vcn: "x2_UzUz_Nigina", name: "Nigina (Uzbek)", gender: "Ayol", info: "Alternativ o'zbek ovozi" },
    { vcn: "aisjiaying", name: "Premium (Multilingual)", gender: "Ayol", info: "Yuqori sifatli talaffuz" },
    { vcn: "aisxping", name: "Standard (Multilingual)", gender: "Erkak", info: "Erkak ovozi" },
    { vcn: "x2_RuRu_Keshu", name: "Russian Keshu", gender: "Ayol", info: "Rus tili uchun hujjatda ko'rsatilgan" },
    { vcn: "rania", name: "Arabic Rania", gender: "Ayol", info: "Arab tili uchun (hujjatda x2_ArEn_Rania)" },
    { vcn: "mohamed", name: "Arabic Mohamed", gender: "Erkak", info: "Arab tili erkak ovozi" },
    { vcn: "x4_lingxiaoyue_oral", name: "Lingxiaoyue (Oral)", gender: "Ayol", info: "Chinese / Oral (Hujjatdagi)" },
    { vcn: "x4_EnUk_Lizzy_assist", name: "EnUk Lizzy", gender: "Ayol", info: "Ingliz tili (UK)" },
    { vcn: "aisjinger", name: "Lively", gender: "Ayol", info: "Quvnoq ohang" },
    { vcn: "aisbabyxu", name: "Child", gender: "Ayol", info: "Bolalar ovozi" }
];

console.log("🎤 iFlytek TTS Mavjud Ovozlar (VCN ro'yxati):");
console.table(IFLYTEK_VOICES);
console.log("💡 Eslatma: O'zbek tili 'mts' engine orqali 'Multilingual' ovozlar bilan eng yaxshi ishlaydi.");

async function getAuthData() {
    const res = await fetch("api/iflytek_auth.php?type=tts");
    return await res.json();
}

export function speakIFlytek(text, langOrVcn = "uz") {
    // Til yoki VCN tanlash mantiqi
    let vcn = "xiaoyan"; // Default
    let lang = langOrVcn;

    // Tekshiramiz: langOrVcn - bu VCN kodimi (x2_..., x4_..., ais...) yoki til kodimi?
    const isVcn = langOrVcn.startsWith("x2_") || 
                  langOrVcn.startsWith("x4_") || 
                  langOrVcn.startsWith("ais") || 
                  ["xiaoyan", "rania", "mohamed"].includes(langOrVcn);

    if (isVcn) {
        vcn = langOrVcn;
        // Tilni VCN dan taxmin qilamiz (log uchun)
        if (vcn.includes("UzUz")) lang = "uz";
        else if (vcn.includes("RuRu")) lang = "ru";
        else if (vcn.includes("EnUk")) lang = "en";
    } else {
        // langOrVcn - bu til kodi (masalan "uz", "ru")
        if (lang === "uz" || lang === "" || !lang) {
            vcn = "x2_UzUz_Nigina";
            lang = "uz";
        } else if (lang === "ru") {
            vcn = "x2_RuRu_Keshu";
        } else if (lang === "ar") {
            vcn = "rania";
        } else if (lang === "en") {
            vcn = "x4_EnUk_Lizzy_assist";
        }
    }

    return new Promise(async (resolve, reject) => {
        try {
            const auth = await getAuthData();
            if (!auth.url || !auth.app_id) {
                throw new Error("iFlytek Auth failed");
            }

            const socket = new WebSocket(auth.url);
            let audioBuffer = [];

            socket.onopen = () => {
                console.log(`🔊 iFlytek TTS [${lang}] -> VCN: ${vcn}`);
                const params = {
                    "common": { "app_id": auth.app_id },
                    "business": {
                        "aue": "raw",
                        "vcn": vcn,
                        "speed": 50,
                        "pitch": 50,
                        "volume": 50,
                        "tte": "UTF8",
                        "ent": "mts",
                        "auf": "audio/L16;rate=16000"
                    },
                    "data": {
                        "status": 2,
                        "text": btoa(unescape(encodeURIComponent(text)))
                    }
                };
                socket.send(JSON.stringify(params));
            };

            socket.onmessage = (e) => {
                const res = JSON.parse(e.data);
                if (res.code !== 0) {
                    console.error("iFlytek TTS Error:", res);
                    socket.close();
                    reject(res);
                    return;
                }

                if (res.data && res.data.audio) {
                    const binary = atob(res.data.audio);
                    const bytes = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) {
                        bytes[i] = binary.charCodeAt(i);
                    }
                    audioBuffer.push(bytes);
                }

                if (res.data && res.data.status === 2) {
                    console.log("🔊 iFlytek Audio qabul qilindi.");
                    socket.close();
                    playBuffer(audioBuffer).then(resolve).catch(reject);
                }
            };

            socket.onerror = (err) => {
                console.error("iFlytek WS Error:", err);
                reject(err);
            };

            socket.onclose = () => {
                console.log("🔊 iFlytek WS Yopildi.");
            };

        } catch (err) {
            reject(err);
        }
    });
}

function playBuffer(chunks) {
    return new Promise((resolve, reject) => {
        if (chunks.length === 0) return resolve();
        
        let totalLength = chunks.reduce((acc, chunk) => acc + chunk.length, 0);
        let combined = new Uint8Array(totalLength);
        let offset = 0;
        for (let chunk of chunks) {
            combined.set(chunk, offset);
            offset += chunk.length;
        }

        // Convert 16-bit PCM to WAV to use with HTML5 Audio
        const wavBlob = encodeWAV(combined, 16000);
        const url = URL.createObjectURL(wavBlob);
        const audio = new Audio(url);
        
        state.currentAudio = audio;
        state.currentAudioUrl = url;

        audio.onended = () => {
            URL.revokeObjectURL(url);
            resolve();
        };
        audio.onerror = (e) => reject(e);
        audio.play().catch(reject);
    });
}

function encodeWAV(samples, sampleRate) {
    const buffer = new ArrayBuffer(44 + samples.length);
    const view = new DataView(buffer);
    
    const writeString = (view, offset, string) => {
        for (let i = 0; i < string.length; i++) view.setUint8(offset + i, string.charCodeAt(i));
    };

    writeString(view, 0, "RIFF");
    view.setUint32(4, 32 + samples.length, true);
    writeString(view, 8, "WAVE");
    writeString(view, 12, "fmt ");
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true); // PCM
    view.setUint16(22, 1, true); // Mono
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(view, 36, "data");
    view.setUint32(40, samples.length, true);

    const samples16 = new Int16Array(samples.buffer);
    for (let i = 0; i < samples16.length; i++) {
        view.setInt16(44 + (i * 2), samples16[i], true);
    }

    return new Blob([view], { type: "audio/wav" });
}
