# 157. Beszélgetés összefoglaló: Graphiti + LangGraph + hangstack ajánlások

## Áttekintés
Az Impi/Core agent jövőbeli fejlesztéseihez beépítettem a dokumentációba az új memória-, multi-agent- és hangtechnológiai javaslatokat.

## Megfigyelések
- `docs/ai-agent-strategy.md` új 17. fejezete részletesen leírja a Graphiti/GraphRAG memóriát, a Zep/Letta/Mem0 alternatívákat, a LangGraph–CrewAI–Autogen orchestrációt, a Wav2Vec2/NeMo + Chatterbox/Orpheus/Octave audio stack-et, valamint a Langfuse alapú megfigyelhetőséget.
- `docs/ai-agent-roadmap.md` bővült egy 10. szekcióval, amely konkrét teendőkre bontja a Graphiti PoC-t, memória-swappable architektúrát, LangGraph flow-t, STT/TTS baselineokat, LiveKit+Pipecat voice pipeline-t és a Langfuse telepítést.
- `notes.md` dokumentálja a frissítéseket, hogy a roadmap és a stratégia bővítése visszakereshető legyen.

## Következő lépések
1. Helyezd át a Graphiti PoC + Langfuse telepítést a sprint backlogba (owner, due date), és indítsd el a Neo4j környezet konfigurálását.
2. Kick-offolj egy LiveKit + Pipecat pilotot, hogy a hangstack valós körülmények között is tesztelhető legyen.
